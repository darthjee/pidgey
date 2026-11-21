<?php

declare(strict_types=1);

namespace Pidgey\Model;

use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * One `idempotency_keys` row: the reservation of a key and, once completed, the stored answer.
 */
final readonly class IdempotencyKey
{
    /**
     * Builds the value from its column values.
     *
     * @param IdempotencyState  $state          Whether the request is still running or has an answer.
     * @param string            $requestHash    Lowercase hex SHA-256 of the normalized payload.
     * @param integer|null      $responseStatus Stored HTTP status, null until completed.
     * @param string|null       $responseBody   Stored JSON body, null until completed.
     * @param DateTimeImmutable $leaseUntil     End of the in-flight lease, in UTC.
     * @param DateTimeImmutable $expiresAt      End of the key's retention, in UTC.
     */
    public function __construct(
        public IdempotencyState $state,
        public string $requestHash,
        public ?int $responseStatus,
        public ?string $responseBody,
        public DateTimeImmutable $leaseUntil,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * Builds the value from a `FETCH_ASSOC` row keyed by column name.
     *
     * @param array<string, mixed> $row Row holding the state, hash, response, lease and expiry columns.
     *
     * @return self
     *
     * @throws LogicException           When a column is missing.
     * @throws UnexpectedValueException When a value has the wrong shape.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            Row::enum($row, 'state', IdempotencyState::class),
            Row::string($row, 'request_hash'),
            Row::nullableInt($row, 'response_status'),
            Row::nullableString($row, 'response_body'),
            Row::dateTime($row, 'lease_until'),
            Row::dateTime($row, 'expires_at'),
        );
    }
}
