<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use JsonException;
use LogicException;
use PDOException;
use Pidgey\Model\ApiToken;
use Pidgey\Repository\ApiTokenRepository;
use Random\RandomException;

/**
 * Lists, creates and revokes the API tokens of an organization.
 *
 * The plain token is generated here, shown once through CreatedApiToken, and only its SHA-256
 * digest is stored.
 */
final class ApiTokens
{
    /**
     * Builds the service.
     *
     * @param ApiTokenRepository $repository The `api_tokens` repository.
     */
    public function __construct(private readonly ApiTokenRepository $repository)
    {
    }

    /**
     * Lists the tokens of an organization, revoked ones included, ordered by id.
     *
     * @param integer $organizationId Organization the tokens belong to.
     *
     * @return ApiToken[]
     *
     * @phpstan-return list<ApiToken>
     *
     * @throws PDOException When the statement fails.
     */
    public function list(int $organizationId): array
    {
        return $this->repository->listByOrganization($organizationId);
    }

    /**
     * Creates a token from already validated input.
     *
     * @param integer  $organizationId Organization the token belongs to.
     * @param string   $name           Label shown in the UI.
     * @param string[] $scopes         Scope names granted to the token.
     *
     * @phpstan-param list<string> $scopes
     *
     * @return CreatedApiToken
     *
     * @throws RandomException When no source of randomness is available.
     * @throws JsonException   When the scopes cannot be encoded as JSON.
     * @throws PDOException    When the statement fails.
     * @throws LogicException  When the inserted row cannot be read back.
     */
    public function create(int $organizationId, string $name, array $scopes): CreatedApiToken
    {
        $plain = ApiTokenValue::generate();
        $token = $this->repository->create(
            $organizationId,
            $name,
            ApiTokenValue::PREFIX,
            ApiTokenValue::hash($plain),
            $scopes,
        );

        return new CreatedApiToken($token, $plain);
    }

    /**
     * Revokes a token of an organization.
     *
     * @param integer $organizationId Organization the token must belong to.
     * @param integer $id             Token id.
     *
     * @return ApiTokenRevokeOutcome
     *
     * @throws PDOException When a statement fails.
     */
    public function revoke(int $organizationId, int $id): ApiTokenRevokeOutcome
    {
        if ($this->repository->revoke($organizationId, $id)) {
            return ApiTokenRevokeOutcome::Revoked;
        }

        return $this->repository->findInOrganization($organizationId, $id) === null
            ? ApiTokenRevokeOutcome::NotFound
            : ApiTokenRevokeOutcome::AlreadyRevoked;
    }
}
