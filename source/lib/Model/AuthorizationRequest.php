<?php

declare(strict_types=1);

namespace Pidgey\Model;

use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * A row of the `authorization_requests` table: one device authorization request.
 *
 * Holds the SHA-256 hex digest of the poll token only, never the plain token.
 */
final readonly class AuthorizationRequest
{
    /**
     * Builds the entity from its column values.
     *
     * @param integer                    $id                      Primary key.
     * @param string                     $uuid                    Public id of the request.
     * @param string                     $username                Username as typed by the requester.
     * @param integer|null               $userId                  Resolved user; null when unknown.
     * @param AuthorizationRequestStatus $status                  Request state.
     * @param string                     $pollTokenHash           SHA-256 hex digest of the poll token.
     * @param string                     $requestIp               Requester's IP address.
     * @param string                     $requestUserAgent        Requester's user agent.
     * @param integer|null               $approvedByUserId        User whose session approved it.
     * @param DateTimeImmutable          $createdAt               Creation time, in UTC.
     * @param DateTimeImmutable          $expiresAt               End of the request's lifetime, in UTC.
     * @param DateTimeImmutable|null     $resolvedAt              Approve / deny / expire time, in UTC.
     * @param DateTimeImmutable|null     $loggedAt                Successful claim time, in UTC.
     * @param integer                    $authorizeFailedAttempts Failed authorize attempts on it.
     * @param DateTimeImmutable|null     $authorizeLockedUntil    Authorize refused until then, in UTC.
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $username,
        public ?int $userId,
        public AuthorizationRequestStatus $status,
        public string $pollTokenHash,
        public string $requestIp,
        public string $requestUserAgent,
        public ?int $approvedByUserId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $resolvedAt,
        public ?DateTimeImmutable $loggedAt,
        public int $authorizeFailedAttempts,
        public ?DateTimeImmutable $authorizeLockedUntil,
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
            Row::string($row, 'uuid'),
            Row::string($row, 'username'),
            Row::nullableInt($row, 'user_id'),
            Row::enum($row, 'status', AuthorizationRequestStatus::class),
            Row::string($row, 'poll_token_hash'),
            Row::string($row, 'request_ip'),
            Row::string($row, 'request_user_agent'),
            Row::nullableInt($row, 'approved_by_user_id'),
            Row::dateTime($row, 'created_at'),
            Row::dateTime($row, 'expires_at'),
            Row::nullableDateTime($row, 'resolved_at'),
            Row::nullableDateTime($row, 'logged_at'),
            Row::int($row, 'authorize_failed_attempts'),
            Row::nullableDateTime($row, 'authorize_locked_until'),
        );
    }
}
