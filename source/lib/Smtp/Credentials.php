<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use LogicException;
use Pidgey\Infra\SecretString;
use SensitiveParameter;

/**
 * The SMTP `AUTH` credentials of a session: a username and a password kept as a
 * `SecretString`.
 *
 * It has no `__toString()` and is not `JsonSerializable`; debug output hides the password,
 * and serializing or cloning throws.
 */
final class Credentials
{
    /**
     * Builds the credentials.
     *
     * @param string       $username The `AUTH` username.
     * @param SecretString $password The `AUTH` password.
     */
    public function __construct(
        private readonly string $username,
        #[SensitiveParameter] private readonly SecretString $password,
    ) {
    }

    /**
     * Returns the username.
     *
     * @return string
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Returns the password, still wrapped.
     *
     * @return SecretString
     */
    public function password(): SecretString
    {
        return $this->password;
    }

    /**
     * Redacts the password in `var_dump()` and `print_r()` output.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['username' => $this->username, 'password' => '[redacted]'];
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
        throw new LogicException('Credentials: not serializable');
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
        throw new LogicException('Credentials: not serializable');
    }

    /**
     * Refuses cloning.
     *
     * @return void
     *
     * @throws LogicException Always.
     */
    public function __clone(): void
    {
        throw new LogicException('Credentials: not cloneable');
    }
}
