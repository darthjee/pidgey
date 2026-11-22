<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Model\ApiToken;

/**
 * The API token authenticated by `ApiTokenAuth` on the `/v1` surface (see `api/authentication/api-tokens.md`).
 *
 * `organizationId` and `scopes` come from the database row, never from the request.
 */
final readonly class AuthenticatedApiToken implements Caller
{
    /**
     * Builds the authenticated token.
     *
     * @param integer  $id             The token id (`api_tokens.id`).
     * @param integer  $organizationId The organization the token belongs to.
     * @param string[] $scopes         Scope names granted to the token.
     *
     * @phpstan-param list<string> $scopes
     */
    public function __construct(
        public int $id,
        public int $organizationId,
        public array $scopes,
    ) {
    }

    /**
     * Builds the authenticated token from its database row.
     *
     * @param ApiToken $token The row found by the middleware.
     *
     * @return self
     */
    public static function fromToken(ApiToken $token): self
    {
        return new self($token->id, $token->organizationId, $token->scopes);
    }

    /**
     * Returns the token's organization.
     *
     * @return integer
     */
    public function organizationId(): int
    {
        return $this->organizationId;
    }

    /**
     * Tells whether the token was granted a scope.
     *
     * @param string $scope Scope name, e.g. `send`.
     *
     * @return boolean
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
