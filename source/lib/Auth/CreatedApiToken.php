<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Model\ApiToken;
use SensitiveParameter;

/**
 * Result of creating an API token: the stored row plus the plain token, shown only once.
 */
final readonly class CreatedApiToken
{
    /**
     * @param ApiToken $apiToken The stored token (digest and prefix only).
     * @param string   $token    The plain token; never stored nor logged.
     */
    public function __construct(
        public ApiToken $apiToken,
        #[SensitiveParameter] public string $token,
    ) {
    }

    /**
     * Hides the plain token from `var_dump` / `print_r`.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['apiToken' => $this->apiToken];
    }
}
