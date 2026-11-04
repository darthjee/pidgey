<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use DateInterval;
use DateTimeImmutable;
use LogicException;
use PDOException;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\WallClock;
use Pidgey\Model\RefreshToken;
use Pidgey\Model\RevokedReason;
use Pidgey\Model\User;
use Pidgey\Repository\RefreshTokenRepository;
use Random\RandomException;

/**
 * The atomic rotation step of a refresh (see `api/authentication/sessions.md#rotation`).
 *
 * In one transaction it inserts the session's next refresh token and conditionally revokes the
 * presented one with `rotated`. When that revoke affects no row (a concurrent refresh won, or
 * the token expired in between), the transaction is rolled back and nothing changes.
 */
final class SessionRotation
{
    /**
     * Builds the rotation step.
     *
     * @param RefreshTokenRepository $refreshTokens The `refresh_tokens` repository.
     * @param Transaction            $transaction   Transaction helper of the same connection.
     * @param AccessToken            $accessToken   Issues the access tokens (JWT).
     * @param WallClock              $clock         Source of `now`, in UTC.
     * @param integer                $refreshTtl    Refresh token lifetime in seconds (`REFRESH_TOKEN_TTL`).
     */
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly Transaction $transaction,
        private readonly AccessToken $accessToken,
        private readonly WallClock $clock,
        private readonly int $refreshTtl,
    ) {
    }

    /**
     * Rotates the presented token: the session continues with a new refresh token and JWT.
     *
     * Opens its own transaction, so it must not run inside another one.
     *
     * @param RefreshToken $presented The presented token, as read before the rotation.
     * @param User         $user      The token's user.
     *
     * @return SessionTokens|null The new tokens, or null when the presented token was no longer
     *                            active (rolled back, no side effect).
     *
     * @throws PDOException    When a statement fails.
     * @throws LogicException  When a transaction is already active on the connection.
     * @throws RandomException When no source of randomness is available.
     */
    public function rotate(RefreshToken $presented, User $user): ?SessionTokens
    {
        $now = $this->clock->now();
        $plain = RefreshTokenValue::generate();
        $hash = RefreshTokenValue::hash($plain);

        try {
            $this->transaction->run(function () use ($presented, $hash, $now): void {
                $this->refreshTokens->create(
                    $hash,
                    $presented->userId,
                    $presented->sessionUuid,
                    $now,
                    $this->expiresAt($now),
                    $presented->startedAt,
                );

                if ($this->refreshTokens->revokeIfActive($presented->id, RevokedReason::Rotated, $now) === 0) {
                    throw new RotationLost();
                }
            });
        } catch (RotationLost) {
            return null;
        }

        return new SessionTokens(
            $plain,
            $this->accessToken->issue($user->id, $presented->sessionUuid, $user->username, $user->isAdmin),
            $presented->sessionUuid,
            $user,
        );
    }

    /**
     * Computes when a refresh token issued at `$now` expires.
     *
     * @param DateTimeImmutable $now Issue time.
     *
     * @return DateTimeImmutable
     */
    private function expiresAt(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->add(new DateInterval('PT' . $this->refreshTtl . 'S'));
    }
}
