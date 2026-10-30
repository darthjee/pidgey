<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use JsonException;
use LogicException;
use PDO;
use PDOException;
use Pidgey\Model\ApiToken;

/**
 * Reads and writes the `api_tokens` table.
 *
 * Stores the SHA-256 hex digest of each token and its non-secret display prefix only, never
 * the plain token: hashing happens in the service, before the call.
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
     * Scope names are stored as given: validating them is the service's job.
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
