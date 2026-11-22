<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\ApiToken;

/**
 * Reads and writes the `api_tokens` table.
 *
 * Stores the SHA-256 hex digest of each token and its non-secret display prefix only, never
 * the plain token: hashing happens in `Pidgey\Auth\ApiTokens`, before the call.
 */
final class ApiTokenRepository
{
    private const COLUMNS = 'id, organization_id, name, token_prefix, token_hash, scopes, created_at, '
        . 'last_used_at, revoked_at';

    /**
     * Creates the repository on the request's connection.
     *
     * @param PDO $pdo Connection to the application database.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserts an API token and returns it, re-read so column defaults (`created_at`) are reflected.
     *
     * Scope names are stored as given: validating them is `ApiTokenRules`' job.
     *
     * @param integer  $organizationId Organization the token belongs to.
     * @param string   $name           Label shown in the UI.
     * @param string   $tokenPrefix    Non-secret display prefix of the token.
     * @param string   $tokenHash      SHA-256 hex digest of the full token.
     * @param string[] $scopes         Scope names granted to the token.
     *
     * @phpstan-param list<string> $scopes
     *
     * @return ApiToken
     *
     * @throws JsonException  When the scopes cannot be encoded as JSON.
     * @throws PDOException   When the statement fails (e.g. a duplicate digest or an unknown organization).
     * @throws LogicException When the inserted row cannot be read back.
     */
    public function create(
        int $organizationId,
        string $name,
        string $tokenPrefix,
        string $tokenHash,
        array $scopes,
    ): ApiToken {
        $this->pdo->prepare(
            'INSERT INTO api_tokens (organization_id, name, token_prefix, token_hash, scopes) '
            . 'VALUES (:organization_id, :name, :token_prefix, :token_hash, :scopes)',
        )->execute([
            'organization_id' => $organizationId,
            'name' => $name,
            'token_prefix' => $tokenPrefix,
            'token_hash' => $tokenHash,
            'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR),
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findInOrganization($organizationId, $id)
            ?? throw new LogicException("API token {$id} was inserted but cannot be read back.");
    }

    /**
     * Finds an API token by id within one organization.
     *
     * @param integer $organizationId Organization the token must belong to.
     * @param integer $id             Primary key.
     *
     * @return ApiToken|null Null when no token of this organization has this id.
     *
     * @throws PDOException When the statement fails.
     */
    public function findInOrganization(int $organizationId, int $id): ?ApiToken
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM api_tokens WHERE organization_id = :organization_id AND id = :id',
            ['organization_id' => $organizationId, 'id' => $id],
        );
    }

    /**
     * Finds an API token by the digest of the token the client presented, revoked or not.
     *
     * Unscoped on purpose: Bearer authentication identifies the organization by the token
     * itself (see api/authentication/api-tokens.md and persistence/repositories.md,
     * "Organization scoping"). Revoked tokens are returned too: rejecting them is the
     * caller's job.
     *
     * @param string $tokenHash SHA-256 hex digest of the presented token.
     *
     * @return ApiToken|null Null when no token has this digest.
     *
     * @throws PDOException When the statement fails.
     */
    public function findByTokenHash(string $tokenHash): ?ApiToken
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM api_tokens WHERE token_hash = :token_hash',
            ['token_hash' => $tokenHash],
        );
    }

    /**
     * Finds an API token by digest, only when its organization has at least one `approved` user.
     *
     * One query (the token lookup joins the approved-user check). Revoked tokens are returned
     * too: rejecting them is the caller's job. Unscoped on purpose, like `findByTokenHash()`.
     *
     * @param string $tokenHash SHA-256 hex digest of the presented token.
     *
     * @return ApiToken|null Null when no token has this digest or its organization has no
     *                       approved user.
     *
     * @throws PDOException When the statement fails.
     */
    public function findByTokenHashWithApprovedUser(string $tokenHash): ?ApiToken
    {
        return $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM api_tokens WHERE token_hash = :token_hash '
            . "AND EXISTS (SELECT 1 FROM users WHERE users.organization_id = api_tokens.organization_id "
            . "AND users.status = 'approved')",
            ['token_hash' => $tokenHash],
        );
    }

    /**
     * Records a successful use of an API token.
     *
     * @param integer           $id  Primary key.
     * @param DateTimeImmutable $now The time of the use.
     *
     * @return void
     *
     * @throws PDOException When the statement fails.
     */
    public function touchLastUsedAt(int $id, DateTimeImmutable $now): void
    {
        $this->pdo->prepare('UPDATE api_tokens SET last_used_at = :now WHERE id = :id')->execute([
            'now' => $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    /**
     * Lists every API token of an organization, revoked ones included, ordered by id.
     *
     * @param integer $organizationId Organization the tokens belong to.
     *
     * @return ApiToken[]
     *
     * @phpstan-return list<ApiToken>
     *
     * @throws PDOException When the statement fails.
     */
    public function listByOrganization(int $organizationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM api_tokens WHERE organization_id = :organization_id ORDER BY id',
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map(
            static fn (array $row): ApiToken => ApiToken::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * Revokes an API token of an organization, only if it is not revoked yet.
     *
     * A single conditional `UPDATE`, so concurrent revokes change the row once.
     *
     * @param integer $organizationId Organization the token must belong to.
     * @param integer $id             Primary key.
     *
     * @return boolean True when this call revoked the token; false when it was unknown, from
     *                 another organization or already revoked.
     *
     * @throws PDOException When the statement fails.
     */
    public function revoke(int $organizationId, int $id): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() '
            . 'WHERE organization_id = :organization_id AND id = :id AND revoked_at IS NULL',
        );
        $statement->execute(['organization_id' => $organizationId, 'id' => $id]);

        return $statement->rowCount() === 1;
    }

    /**
     * Runs a single-row query and builds the API token, if any.
     *
     * @param string               $sql    Statement with named placeholders.
     * @param array<string, mixed> $params Placeholder name => value.
     *
     * @return ApiToken|null
     *
     * @throws PDOException When the statement fails.
     */
    private function fetchOne(string $sql, array $params): ?ApiToken
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ApiToken::fromRow($row);
    }
}
