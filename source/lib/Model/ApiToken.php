<?php

declare(strict_types=1);

namespace Pidgey\Model;

use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * A row of the `api_tokens` table: one organization API token.
 *
 * Holds the SHA-256 hex digest of the token and its non-secret display prefix only, never the
 * plain token.
 */
final readonly class ApiToken
{
    /**
     * Builds the entity from its column values.
     *
     * @param integer                $id             Primary key.
     * @param integer                $organizationId Organization the token belongs to.
     * @param string                 $name           Label shown in the UI.
     * @param string                 $tokenPrefix    Non-secret display prefix, for identification.
     * @param string                 $tokenHash      SHA-256 hex digest of the full token (the stored
     *                                               digest, never the plain token).
     * @param string[]               $scopes         Scope names granted to the token.
     * @param DateTimeImmutable      $createdAt      Creation time, in UTC.
     * @param DateTimeImmutable|null $lastUsedAt     Last successful use, in UTC.
     * @param DateTimeImmutable|null $revokedAt      Revocation time, in UTC.
     *
     * @phpstan-param list<string> $scopes
     */
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $name,
        public string $tokenPrefix,
        public string $tokenHash,
        public array $scopes,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt,
        public ?DateTimeImmutable $revokedAt,
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
     * @throws UnexpectedValueException When a value has the wrong shape (e.g. `scopes` that is not
     *                                  a JSON list of strings).
     */
    public static function fromRow(array $row): self
    {
        return new self(
            Row::int($row, 'id'),
            Row::int($row, 'organization_id'),
            Row::string($row, 'name'),
            Row::string($row, 'token_prefix'),
            Row::string($row, 'token_hash'),
            Row::stringList($row, 'scopes'),
            Row::dateTime($row, 'created_at'),
            Row::nullableDateTime($row, 'last_used_at'),
            Row::nullableDateTime($row, 'revoked_at'),
        );
    }
}
