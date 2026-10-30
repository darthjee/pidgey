<?php

declare(strict_types=1);

namespace Pidgey\Model;

use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * A row of the `smtp_connections` table: one organization SMTP connection.
 *
 * Holds the encrypted password bytes only, never a plain-text password.
 */
final readonly class SmtpConnection
{
    /**
     * Builds the entity from its column values.
     *
     * @param integer           $id                Primary key.
     * @param integer           $organizationId    Organization the connection belongs to.
     * @param string            $name              Label shown in the UI.
     * @param string            $host              SMTP server hostname.
     * @param integer           $port              SMTP port.
     * @param SmtpEncryption    $encryption        Transport encryption.
     * @param string|null       $username          SMTP auth username.
     * @param string|null       $encryptedPassword Ciphertext bytes of the SMTP auth password, as
     *                                             stored in the `password` column (never plain
     *                                             text).
     * @param string            $fromAddress       Envelope sender and `From:` header.
     * @param boolean           $active            Whether this is the organization's active
     *                                             connection.
     * @param DateTimeImmutable $createdAt         Creation time, in UTC.
     * @param DateTimeImmutable $updatedAt         Last update time, in UTC.
     */
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $name,
        public string $host,
        public int $port,
        public SmtpEncryption $encryption,
        public ?string $username,
        public ?string $encryptedPassword,
        public string $fromAddress,
        public bool $active,
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
     * @throws UnexpectedValueException When a value has the wrong shape (e.g. an unknown
     *                                  encryption).
     */
    public static function fromRow(array $row): self
    {
        return new self(
            Row::int($row, 'id'),
            Row::int($row, 'organization_id'),
            Row::string($row, 'name'),
            Row::string($row, 'host'),
            Row::int($row, 'port'),
            Row::enum($row, 'encryption', SmtpEncryption::class),
            Row::nullableString($row, 'username'),
            Row::nullableString($row, 'password'),
            Row::string($row, 'from_address'),
            Row::bool($row, 'active'),
            Row::dateTime($row, 'created_at'),
            Row::dateTime($row, 'updated_at'),
        );
    }

    /**
     * Tells whether the connection has a stored (encrypted) password.
     *
     * @return boolean
     */
    public function hasPassword(): bool
    {
        return $this->encryptedPassword !== null;
    }
}
