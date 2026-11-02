<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Infra\Env;
use SensitiveParameter;

/**
 * The access-token keys: the current `SECRET_KEY` signs and verifies, the retired
 * `PREVIOUS_SECRET_KEYS` only verify (see `api/authentication/access-token.md#key-rotation`).
 *
 * The keys are never logged or returned; they are hidden from debug dumps.
 */
final readonly class SecretKeys
{
    /**
     * Builds the key set.
     *
     * @param string   $current  The signing key.
     * @param string[] $previous Retired keys, accepted only to verify, in order.
     */
    public function __construct(
        #[SensitiveParameter] public string $current,
        #[SensitiveParameter] public array $previous = [],
    ) {
    }

    /**
     * Reads `SECRET_KEY` and `PREVIOUS_SECRET_KEYS` from the environment.
     *
     * Entries of `PREVIOUS_SECRET_KEYS` are trimmed and empty entries ignored; the boot
     * validation has already refused invalid values.
     *
     * @return self
     */
    public static function fromEnv(): self
    {
        $previous = array_map('trim', explode(',', (string) Env::get('PREVIOUS_SECRET_KEYS', '')));

        return new self(
            Env::required('SECRET_KEY'),
            array_values(array_filter($previous, static fn (string $key): bool => $key !== '')),
        );
    }

    /**
     * Returns the keys to try when verifying: the current key first, then each previous key.
     *
     * @return list<string>
     */
    public function verificationKeys(): array
    {
        return [$this->current, ...$this->previous];
    }

    /**
     * Hides the keys from var_dump() and similar dumps.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['current' => '[redacted]', 'previous' => '[redacted]'];
    }
}
