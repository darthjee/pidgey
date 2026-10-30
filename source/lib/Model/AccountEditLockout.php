<?php

declare(strict_types=1);

namespace Pidgey\Model;

use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * A row of the `account_edit_lockouts` table: the failed account-edit counter of one user.
 */
final readonly class AccountEditLockout
{
    /**
     * Builds the entity from its column values.
     *
     * @param integer                $id             Primary key.
     * @param integer                $userId         User the counter belongs to.
     * @param integer                $failedAttempts Consecutive failed account-edit attempts.
     * @param DateTimeImmutable|null $lockedUntil    Account edits are refused until then, in UTC.
     * @param DateTimeImmutable      $createdAt      Creation time, in UTC.
     * @param DateTimeImmutable      $updatedAt      Last update time, in UTC.
     */
    public function __construct(
        public int $id,
        public int $userId,
        public int $failedAttempts,
        public ?DateTimeImmutable $lockedUntil,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
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
            Row::int($row, 'user_id'),
            Row::int($row, 'failed_attempts'),
            Row::nullableDateTime($row, 'locked_until'),
            Row::dateTime($row, 'created_at'),
            Row::dateTime($row, 'updated_at'),
        );
    }
}
