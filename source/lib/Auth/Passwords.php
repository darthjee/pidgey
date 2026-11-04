<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use SensitiveParameter;

/**
 * Hashes and checks passwords (see `api/endpoints/auth.md#password-hashing`).
 *
 * Verification always runs exactly one `password_verify()`: when there is no user, it runs
 * against `DUMMY_DIGEST`, so the response time does not reveal whether a username exists.
 */
final class Passwords implements PasswordVerifier
{
    /**
     * A hard-coded `PASSWORD_DEFAULT` digest of a random, discarded string (bcrypt, cost 12).
     *
     * Never computed at runtime. A unit test asserts that `password_needs_rehash()` is false for
     * it, so a change of PHP's default algorithm or cost fails the suite instead of silently
     * making unknown-user logins faster than real ones.
     */
    public const DUMMY_DIGEST = '$2y$12$mV.fFfQHSZaUZCy60Qny.ukI2luAYMAjAUIHV3tud1o38gSktFWfG';

    /** The longest password bcrypt hashes in full; longer ones would be silently truncated. */
    public const MAX_BYTES = 72;

    /**
     * Hashes a new password with `PASSWORD_DEFAULT`.
     *
     * @param string $password The plain password (at most 72 bytes, no NUL byte).
     *
     * @return string The digest.
     */
    public function hash(#[SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Tells whether `hash()` can digest a password in full: at most `MAX_BYTES` bytes and no NUL
     * byte (`password_hash()` throws a `ValueError` on a NUL byte). These are the register rules.
     *
     * @param string $password The plain password, as sent.
     *
     * @return boolean
     */
    public function canHash(#[SensitiveParameter] string $password): bool
    {
        return strlen($password) <= self::MAX_BYTES && !str_contains($password, "\0");
    }

    /**
     * Checks a password against a user's digest, or against `DUMMY_DIGEST` when there is no user.
     *
     * @param string      $password The plain password, as sent.
     * @param string|null $digest   The user's digest, or null when the username is unknown.
     *
     * @return boolean True only when a real digest matches.
     */
    public function verify(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $digest): bool
    {
        $matches = password_verify($password, $digest ?? self::DUMMY_DIGEST);

        return $matches && $digest !== null;
    }

    /**
     * Tells whether a digest should be replaced by a fresh `PASSWORD_DEFAULT` one.
     *
     * @param string $digest The user's current digest.
     *
     * @return boolean
     */
    public function needsRehash(#[SensitiveParameter] string $digest): bool
    {
        return password_needs_rehash($digest, PASSWORD_DEFAULT);
    }
}
