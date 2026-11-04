<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use SensitiveParameter;

/**
 * Checks a plain password against a digest (see `api/endpoints/auth.md#password-hashing`).
 *
 * Implemented by Passwords; a seam so services can be tested with a counting double.
 */
interface PasswordVerifier
{
    /**
     * Checks a password against a user's digest, or against a dummy digest when there is none.
     *
     * @param string      $password The plain password, as sent.
     * @param string|null $digest   The user's digest, or null when there is no user.
     *
     * @return boolean True only when a real digest matches.
     */
    public function verify(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $digest): bool;
}
