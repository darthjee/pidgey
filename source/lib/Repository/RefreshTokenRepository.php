<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use DateTimeImmutable;
use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\RefreshToken;
use Pidgey\Model\RevokedReason;

/**
 * Reads and writes the `refresh_tokens` table.
 *
 * Stores and looks up the SHA-256 hex digest of each refresh token, never the plain token:
 * hashing happens in the service, before the call.
 */
final class RefreshTokenRepository
{
    private const COLUMNS = 'id, token_hash, user_id, issued_at, expires_at, revoked_at, revoked_reason, '
        . 'session_uuid, started_at';

    /**
     * Creates the repository on the request's connection.
     *
     * @param PDO $pdo Connection to the application database.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserts a live (not revoked) refresh token and returns it, re-read from the database.
     *
     * @param string            $tokenHash   SHA-256 hex digest of the refresh token.
     * @param integer           $userId      Owner of the session.
     * @param string            $sessionUuid Session id, shared by every token of the session.
     * @param DateTimeImmutable $issuedAt    When this token is issued, from `WallClock`.
     * @param DateTimeImmutable $expiresAt   When this token stops being accepted.
     * @param DateTimeImmutable $startedAt   When the session's login happened.
     *
     * @return RefreshToken
     *
     * @throws PDOException   When the statement fails (e.g. a duplicate digest or an unknown user).
     * @throws LogicException When the inserted row cannot be read back.
     */
    public function create(
        string $tokenHash,
        int $userId,
        string $sessionUuid,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $startedAt,
    ): RefreshToken {
        $this->pdo->prepare(
            'INSERT INTO refresh_tokens (token_hash, user_id, issued_at, expires_at, session_uuid, started_at) '
            . 'VALUES (:token_hash, :user_id, :issued_at, :expires_at, :session_uuid, :started_at)',
        )->execute([
            'token_hash' => $tokenHash,
            'user_id' => $userId,
            'issued_at' => Sql::dateTime($issuedAt),
            'expires_at' => Sql::dateTime($expiresAt),
            'session_uuid' => $sessionUuid,
            'started_at' => Sql::dateTime($startedAt),
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id)
            ?? throw new LogicException("Refresh token {$id} was inserted but cannot be read back.");
    }

    /**
     * Finds a refresh token by id.
     *
     * Unscoped on purpose: refresh tokens belong to a user, not directly to an organization,
     * and are read before any organization is known (see api/authentication/sessions.md).
     *
     * @param integer $id Primary key.
     *
     * @return RefreshToken|null Null when no refresh token has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function findById(int $id): ?RefreshToken
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM refresh_tokens WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * Finds a refresh token by the digest of the token the client presented.
     *
     * Unscoped on purpose: refresh and logout identify the session by its token alone (see
     * api/authentication/sessions.md).
     *
     * @param string $tokenHash SHA-256 hex digest of the presented token.
     *
     * @return RefreshToken|null Null when no refresh token has this digest.
     *
     * @throws PDOException When the statement fails.
     */
    public function findByTokenHash(string $tokenHash): ?RefreshToken
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM refresh_tokens WHERE token_hash = :token_hash',
            ['token_hash' => $tokenHash],
        );
    }

    /**
     * Revokes one refresh token, only if it is still active (not revoked and not expired at `$now`).
     *
     * This is the conditional revoke of rotation: 0 rows means a concurrent request revoked the
     * row first, or it expired, and the caller must not treat the token as rotated.
     *
     * @param integer           $id     Primary key.
     * @param RevokedReason     $reason Why the token is revoked; written with `revoked_at`.
     * @param DateTimeImmutable $now    Current time, from `WallClock`.
     *
     * @return integer Number of rows revoked (0 or 1).
     *
     * @throws PDOException When the statement fails.
     */
    public function revokeIfActive(int $id, RevokedReason $reason, DateTimeImmutable $now): int
    {
        return $this->execute(
            'UPDATE refresh_tokens SET revoked_at = :revoked_at, revoked_reason = :reason '
            . 'WHERE id = :id AND revoked_at IS NULL AND expires_at > :now',
            [
                'revoked_at' => Sql::dateTime($now),
                'reason' => $reason->value,
                'id' => $id,
                'now' => Sql::dateTime($now),
            ],
        );
    }

    /**
     * Revokes every active (not yet revoked) refresh token of a user.
     *
     * Already-revoked rows keep their original time and reason.
     *
     * @param integer           $userId Owner of the tokens.
     * @param RevokedReason     $reason Why the tokens are revoked; written with `revoked_at`.
     * @param DateTimeImmutable $now    Current time, from `WallClock`.
     *
     * @return integer Number of rows revoked.
     *
     * @throws PDOException When the statement fails.
     */
    public function revokeActiveForUser(int $userId, RevokedReason $reason, DateTimeImmutable $now): int
    {
        return $this->execute(
            'UPDATE refresh_tokens SET revoked_at = :revoked_at, revoked_reason = :reason '
            . 'WHERE user_id = :user_id AND revoked_at IS NULL',
            ['revoked_at' => Sql::dateTime($now), 'reason' => $reason->value, 'user_id' => $userId],
        );
    }

    /**
     * Revokes every active (not yet revoked) refresh token of one session of a user.
     *
     * @param integer           $userId      Owner of the session.
     * @param string            $sessionUuid Session id.
     * @param RevokedReason     $reason      Why the tokens are revoked; written with `revoked_at`.
     * @param DateTimeImmutable $now         Current time, from `WallClock`.
     *
     * @return integer Number of rows revoked.
     *
     * @throws PDOException When the statement fails.
     */
    public function revokeActiveForSession(
        int $userId,
        string $sessionUuid,
        RevokedReason $reason,
        DateTimeImmutable $now,
    ): int {
        return $this->execute(
            'UPDATE refresh_tokens SET revoked_at = :revoked_at, revoked_reason = :reason '
            . 'WHERE user_id = :user_id AND session_uuid = :session_uuid AND revoked_at IS NULL',
            [
                'revoked_at' => Sql::dateTime($now),
                'reason' => $reason->value,
                'user_id' => $userId,
                'session_uuid' => $sessionUuid,
            ],
        );
    }

    /**
     * Runs a single-row query and builds the refresh token, if any.
     *
     * @param string               $sql    Statement with named placeholders.
     * @param array<string, mixed> $params Placeholder name => value.
     *
     * @return RefreshToken|null
     *
     * @throws PDOException When the statement fails.
     */
    private function fetchOne(string $sql, array $params): ?RefreshToken
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : RefreshToken::fromRow($row);
    }

    /**
     * Runs a data-changing statement and returns the number of rows it affected.
     *
     * @param string               $sql    Statement with named placeholders.
     * @param array<string, mixed> $params Placeholder name => value.
     *
     * @return integer
     *
     * @throws PDOException When the statement fails.
     */
    private function execute(string $sql, array $params): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }
}
