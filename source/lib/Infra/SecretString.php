<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use LogicException;
use SensitiveParameter;

/**
 * Holds a secret (an SMTP password) so that it never leaks by accident.
 *
 * The value is only readable through `reveal()`. Debug output is redacted, and casting,
 * serializing, cloning and JSON encoding never expose it: there is no `__toString()`, and the
 * value is a private property, so `json_encode()` gives `{}`. `wipe()` overwrites the value in
 * memory once it is no longer needed.
 */
final class SecretString
{
    /** The secret; null once wiped (`sodium_memzero()` nulls the variable). */
    private ?string $value;

    private bool $wiped = false;

    /**
     * Wraps the secret.
     *
     * @param string $value The secret.
     */
    public function __construct(#[SensitiveParameter] string $value)
    {
        $this->value = $value;
    }

    /**
     * Returns the secret.
     *
     * @return string
     *
     * @throws LogicException When the secret was wiped.
     */
    public function reveal(): string
    {
        if ($this->wiped) {
            throw new LogicException('SecretString: wiped');
        }

        return (string) $this->value;
    }

    /**
     * Overwrites the secret in memory; later `reveal()` calls throw. Idempotent.
     *
     * @return void
     */
    public function wipe(): void
    {
        if ($this->wiped) {
            return;
        }

        sodium_memzero($this->value);
        $this->wiped = true;
    }

    /**
     * Redacts the secret in `var_dump()` and `print_r()` output.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }

    /**
     * Refuses serialization.
     *
     * @return never
     *
     * @throws LogicException Always.
     */
    public function __serialize(): never
    {
        throw new LogicException('SecretString: not serializable');
    }

    /**
     * Refuses unserialization.
     *
     * @param array<string, mixed> $data Serialized data.
     *
     * @return void
     *
     * @throws LogicException Always.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('SecretString: not serializable');
    }

    /**
     * Refuses cloning, so that a wiped secret leaves no copy behind.
     *
     * @return void
     *
     * @throws LogicException Always.
     */
    public function __clone(): void
    {
        throw new LogicException('SecretString: not cloneable');
    }
}
