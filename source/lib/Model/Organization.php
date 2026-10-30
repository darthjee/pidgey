<?php

declare(strict_types=1);

namespace Pidgey\Model;

use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * A row of the `organizations` table.
 */
final readonly class Organization
{
    /**
     * Builds the entity from its column values.
     *
     * @param integer           $id        Primary key.
     * @param string            $name      Organization name.
     * @param DateTimeImmutable $createdAt Creation time, in UTC.
     * @param DateTimeImmutable $updatedAt Last update time, in UTC.
     */
    public function __construct(
        public int $id,
        public string $name,
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
            Row::string($row, 'name'),
            Row::dateTime($row, 'created_at'),
            Row::dateTime($row, 'updated_at'),
        );
    }
}
