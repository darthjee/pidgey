<?php

declare(strict_types=1);

namespace Pidgey\Model;

use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * A row of the `users` table.
 *
 * Holds the password digest only, never a plain-text password.
 */
final readonly class User
{
    /**
     * Builds the entity from its column values.
     *
     * @param integer           $id             Primary key.
     * @param integer           $organizationId Organization the user belongs to.
     * @param string            $username       Login name.
     * @param string            $email          Recovery email address.
     * @param string            $passwordDigest Digest of the password, from `password_hash()`.
     * @param boolean           $isAdmin        Whether the user is the global instance admin.
     * @param UserStatus        $status         Approval status.
     * @param DateTimeImmutable $createdAt      Creation time, in UTC.
     * @param DateTimeImmutable $updatedAt      Last update time, in UTC.
     */
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $username,
        public string $email,
        public string $passwordDigest,
        public bool $isAdmin,
        public UserStatus $status,
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
     * @throws UnexpectedValueException When a value has the wrong shape (e.g. an unknown status).
     */
    public static function fromRow(array $row): self
    {
        return new self(
            Row::int($row, 'id'),
            Row::int($row, 'organization_id'),
            Row::string($row, 'username'),
            Row::string($row, 'email'),
            Row::string($row, 'password_digest'),
            Row::bool($row, 'is_admin'),
            Row::enum($row, 'status', UserStatus::class),
            Row::dateTime($row, 'created_at'),
            Row::dateTime($row, 'updated_at'),
        );
    }
}
