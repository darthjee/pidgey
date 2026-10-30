<?php

declare(strict_types=1);

namespace Pidgey\Model;

use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * A row of the `refresh_tokens` table: one refresh token of an admin UI session.
 *
 * Holds the SHA-256 hex digest of the token only, never the plain token.
 */
final readonly class RefreshToken
{
    /**
     * Builds the entity from its column values.
     *
     * @param integer                $id            Primary key.
     * @param string                 $tokenHash     SHA-256 hex digest of the refresh token.
     * @param integer                $userId        Owner of the session.
     * @param DateTimeImmutable      $issuedAt      When this token was issued, in UTC.
     * @param DateTimeImmutable      $expiresAt     When this token stops being accepted, in UTC.
     * @param DateTimeImmutable|null $revokedAt     When this token was revoked, in UTC.
     * @param RevokedReason|null     $revokedReason Why this token was revoked.
     * @param string                 $sessionUuid   Session id, shared by every token of the session.
     * @param DateTimeImmutable      $startedAt     When the session's login happened, in UTC.
     */
    public function __construct(
        public int $id,
        public string $tokenHash,
        public int $userId,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $revokedAt,
        public ?RevokedReason $revokedReason,
        public string $sessionUuid,
        public DateTimeImmutable $startedAt,
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
     * @throws UnexpectedValueException When a value has the wrong shape (e.g. an unknown reason).
     */
    public static function fromRow(array $row): self
    {
        return new self(
            Row::int($row, 'id'),
            Row::string($row, 'token_hash'),
            Row::int($row, 'user_id'),
            Row::dateTime($row, 'issued_at'),
            Row::dateTime($row, 'expires_at'),
            Row::nullableDateTime($row, 'revoked_at'),
            Row::nullableEnum($row, 'revoked_reason', RevokedReason::class),
            Row::string($row, 'session_uuid'),
            Row::dateTime($row, 'started_at'),
        );
    }
}
