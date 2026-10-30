<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use DateTimeImmutable;
use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\PasswordResetToken;

/**
 * Reads and writes the `password_reset_tokens` table.
 *
 * Stores and looks up the SHA-256 hex digest of each reset token, never the plain token:
 * hashing happens in the service, before the call.
 */
final class PasswordResetTokenRepository
{
    private const COLUMNS = 'id, token_hash, user_id, created_at, expires_at, used_at';

    /**
     * Creates the repository on the request's connection.
     *
     * @param PDO $pdo Connection to the application database.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserts an unused reset token and returns it, re-read from the database.
     *
     * @param string            $tokenHash SHA-256 hex digest of the reset token.
     * @param integer           $userId    Account being recovered.
     * @param DateTimeImmutable $createdAt Creation time, from `WallClock` (recover cooldown).
     * @param DateTimeImmutable $expiresAt End of the token's lifetime.
     *
     * @return PasswordResetToken
     *
     * @throws PDOException   When the statement fails (e.g. a duplicate digest or an unknown user).
     * @throws LogicException When the inserted row cannot be read back.
     */
    public function create(
        string $tokenHash,
        int $userId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): PasswordResetToken {
        $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (token_hash, user_id, created_at, expires_at) '
            . 'VALUES (:token_hash, :user_id, :created_at, :expires_at)',
        )->execute([
            'token_hash' => $tokenHash,
            'user_id' => $userId,
            'created_at' => Sql::dateTime($createdAt),
            'expires_at' => Sql::dateTime($expiresAt),
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id)
            ?? throw new LogicException("Password reset token {$id} was inserted but cannot be read back.");
    }

    /**
     * Finds a reset token by id.
     *
     * Unscoped on purpose: recovery runs before any session, so no organization is known
     * (see api/endpoints/auth/recovery.md).
     *
     * @param integer $id Primary key.
     *
     * @return PasswordResetToken|null Null when no reset token has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function findById(int $id): ?PasswordResetToken
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM password_reset_tokens WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * Finds a reset token by the digest of the token the client presented.
     *
     * Unscoped on purpose: a reset identifies the account by its token alone (see
     * api/endpoints/auth/recovery.md).
     *
     * @param string $tokenHash SHA-256 hex digest of the presented token.
     *
     * @return PasswordResetToken|null Null when no reset token has this digest.
     *
     * @throws PDOException When the statement fails.
     */
    public function findByTokenHash(string $tokenHash): ?PasswordResetToken
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM password_reset_tokens WHERE token_hash = :token_hash',
            ['token_hash' => $tokenHash],
        );
    }

    /**
     * Runs a single-row query and builds the reset token, if any.
     *
     * @param string               $sql    Statement with named placeholders.
     * @param array<string, mixed> $params Placeholder name => value.
     *
     * @return PasswordResetToken|null
     *
     * @throws PDOException When the statement fails.
     */
    private function fetchOne(string $sql, array $params): ?PasswordResetToken
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : PasswordResetToken::fromRow($row);
    }
}
