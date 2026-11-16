<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Closure;
use Pidgey\Auth\PasswordVerifier;
use Pidgey\Auth\Passwords;
use SensitiveParameter;

/**
 * Password verifier that runs a callback before each check, then delegates to the real one.
 */
final class CountingPasswordVerifier implements PasswordVerifier
{
    /** @param Closure(): void $onVerify */
    public function __construct(private readonly Closure $onVerify)
    {
    }

    public function verify(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $digest): bool
    {
        ($this->onVerify)();

        return (new Passwords())->verify($password, $digest);
    }
}
