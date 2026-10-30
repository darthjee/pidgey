<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use DateTimeImmutable;
use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\RefreshToken;

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
}
