<?php

declare(strict_types=1);

namespace Pidgey\Model;

use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * A row of the `password_reset_tokens` table: one account recovery token.
 *
 * Holds the SHA-256 hex digest of the token only, never the plain token.
 */
final readonly class PasswordResetToken
{
    /**
     * Builds the entity from its column values.
     *
     * @param integer                $id        Primary key.
     * @param string                 $tokenHash SHA-256 hex digest of the reset token.
     * @param integer                $userId    Account being recovered.
     * @param DateTimeImmutable      $createdAt Creation time, in UTC.
     * @param DateTimeImmutable      $expiresAt End of the token's lifetime, in UTC.
     * @param DateTimeImmutable|null $usedAt    When the token was used (or killed), in UTC.
     */
    public function __construct(
        public int $id,
        public string $tokenHash,
        public int $userId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt,
    ) {
    }

    /**
     * Builds the entity from a `FETCH_ASSOC` row keyed by column name.
     *
     * @param array<string, mixed> $row Row holding every column of the table.
     *
     * @return self
     *
     * @throws LogicException           When a column is missing.
     * @throws UnexpectedValueException When a value has the wrong shape.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            Row::int($row, 'id'),
            Row::string($row, 'token_hash'),
            Row::int($row, 'user_id'),
            Row::dateTime($row, 'created_at'),
            Row::dateTime($row, 'expires_at'),
            Row::nullableDateTime($row, 'used_at'),
        );
    }
}
