<?php

declare(strict_types=1);

namespace Pidgey\Http;

use JsonSerializable;
use Pidgey\Model\ApiToken;
use SensitiveParameter;

/**
 * The API token item of the `/api-tokens*.json` bodies (see
 * `api/endpoints/pidgey-admin/api-tokens.md#api-token-item`).
 *
 * Never carries the digest nor the organization id; the plain token only in the create response.
 */
final readonly class ApiTokenPayload implements JsonSerializable
{
    /**
     * Wraps a token.
     *
     * @param ApiToken    $apiToken The stored row.
     * @param string|null $token    The plain token, only for the create response.
     */
    public function __construct(
        private ApiToken $apiToken,
        #[SensitiveParameter] private ?string $token = null,
    ) {
    }

    /**
     * Builds the item.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $item = $this->item();

        if ($this->token !== null) {
            $item['token'] = $this->token;
        }

        return $item;
    }

    /**
     * Debug view without the plain token.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return $this->item();
    }

    /**
     * Builds the item without the plain token.
     *
     * @return array<string, mixed>
     */
    private function item(): array
    {
        $token = $this->apiToken;

        return [
            'id' => $token->id,
            'name' => $token->name,
            'tokenPrefix' => $token->tokenPrefix,
            'scopes' => $token->scopes,
            'createdAt' => Timestamp::format($token->createdAt),
            'lastUsedAt' => $token->lastUsedAt === null ? null : Timestamp::format($token->lastUsedAt),
            'revokedAt' => $token->revokedAt === null ? null : Timestamp::format($token->revokedAt),
        ];
    }
}
