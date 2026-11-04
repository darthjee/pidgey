<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use DateInterval;
use DateTimeImmutable;
use LogicException;
use PDOException;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\WallClock;
use Pidgey\Model\RevokedReason;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\UserRepository;
use Random\RandomException;
use SensitiveParameter;

/**
 * Owns the refresh-token rules of admin UI sessions: start, rotation, replay detection and
 * revocation (see `api/authentication/sessions.md`).
 *
 * Plain refresh tokens and access tokens are never stored, logged or put in exception messages.
 * Every `now` is read once per operation from the wall clock and bound into SQL.
 */
final class Sessions
{
    /**
     * Builds the service.
     *
     * @param RefreshTokenRepository $refreshTokens The `refresh_tokens` repository.
     * @param UserRepository         $users         The `users` repository.
     * @param Transaction            $transaction   Transaction helper of the same connection.
     * @param SessionRotation        $rotation      The atomic rotation step of a refresh.
     * @param AccessToken            $accessToken   Issues the access tokens (JWT).
     * @param WallClock              $clock         Source of `now`, in UTC.
     * @param integer                $refreshTtl    Refresh token lifetime in seconds (`REFRESH_TOKEN_TTL`).
     */
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly UserRepository $users,
        private readonly Transaction $transaction,
        private readonly SessionRotation $rotation,
        private readonly AccessToken $accessToken,
        private readonly WallClock $clock,
        private readonly int $refreshTtl,
    ) {
    }

    /**
     * Starts a new session for the user (login, register, winning device poll).
     *
     * Opens no transaction of its own: the caller may run it inside one (register does), since
     * transactions are not nested. It does not check the user's status; the caller decides
     * whether a session may start.
     *
     * @param User $user The session's user.
     *
     * @return SessionTokens The plain refresh token, the access token and the session id.
     *
     * @throws PDOException    When a statement fails.
     * @throws RandomException When no source of randomness is available.
     */
    public function start(User $user): SessionTokens
    {
        $now = $this->clock->now();
        $plain = RefreshTokenValue::generate();
        $sessionUuid = SessionUuid::generate();

        $this->refreshTokens->create(
            RefreshTokenValue::hash($plain),
            $user->id,
            $sessionUuid,
            $now,
            $this->expiresAt($now),
            $now,
        );

        return new SessionTokens(
            $plain,
            $this->accessToken->issue($user->id, $sessionUuid, $user->username, $user->isAdmin),
            $sessionUuid,
            $user,
        );
    }

    /**
     * Refreshes a session: rotates the presented refresh token, following the order of the
     * refresh table of `api/endpoints/auth/session.md`.
     *
     * Every failure is the same null, so callers cannot tell the cases apart:
     * - an unknown or expired token;
     * - a token revoked as `rotated`, a replay: every active token of the user is revoked with
     *   `replay_detected` first (committed);
     * - a token revoked for any other reason;
     * - a missing user;
     * - a denied user: the presented token is revoked with `admin_denied` first (committed);
     * - a lost rotation race (rolled back, no side effect).
     *
     * Opens its own transaction for the rotation, so it must not run inside another one.
     *
     * @param string $plain The plain refresh token presented by the client.
     *
     * @return SessionTokens|null The session's new tokens, or null on any failure.
     *
     * @throws PDOException    When a statement fails.
     * @throws LogicException  When a transaction is already active on the connection.
     * @throws RandomException When no source of randomness is available.
     */
    public function refresh(#[SensitiveParameter] string $plain): ?SessionTokens
    {
        $now = $this->clock->now();
        $token = $this->refreshTokens->findByTokenHash(RefreshTokenValue::hash($plain));

        if ($token === null || $token->expiresAt <= $now) {
            return null;
        }

        if ($token->revokedReason === RevokedReason::Rotated) {
            $this->refreshTokens->revokeActiveForUser($token->userId, RevokedReason::ReplayDetected, $now);

            return null;
        }

        if ($token->revokedAt !== null) {
            return null;
        }

        $user = $this->users->findById($token->userId);

        if ($user === null) {
            return null;
        }

        if ($user->status === UserStatus::Denied) {
            $this->refreshTokens->revokeIfActive($token->id, RevokedReason::AdminDenied, $now);

            return null;
        }

        return $this->rotation->rotate($token, $user);
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
