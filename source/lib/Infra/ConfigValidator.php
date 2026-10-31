<?php

declare(strict_types=1);

namespace Pidgey\Infra;

/**
 * Checks every configuration rule that refuses boot (see `architecture/configuration.md`).
 *
 * Failures are reported as `<CHECK>: <reason>` lines that name the setting and never include
 * its value. Unset or empty optional settings fall back to their defaults and pass.
 */
final class ConfigValidator
{
    public const SECRET_KEY_MIN_BYTES = 32;

    /** Settings that must be set and non-empty. */
    private const REQUIRED = ['DB_HOST', 'DB_NAME', 'DB_USER'];

    /** Durations and counts: positive integers when set. */
    private const POSITIVE_INTEGERS = [
        'DB_WAIT_TIMEOUT',
        'MIGRATE_LOCK_TIMEOUT',
        'ACCESS_TOKEN_TTL',
        'REFRESH_TOKEN_TTL',
        'AUTHORIZATION_REQUEST_TTL',
        'AUTHORIZATION_REQUEST_CREATE_LIMIT',
        'AUTHORIZATION_REQUEST_CREATE_WINDOW',
        'AUTHORIZATION_REQUEST_MAX_OPEN_PER_USER',
        'AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS',
        'AUTHORIZATION_REQUEST_AUTHORIZE_LOCK_TTL',
        'PASSWORD_RESET_TOKEN_TTL',
        'PASSWORD_RESET_COOLDOWN',
        'ACCOUNT_EDIT_MAX_ATTEMPTS',
        'ACCOUNT_EDIT_LOCK_TTL',
    ];

    /** Counts that may be zero: non-negative integers when set. */
    private const NON_NEGATIVE_INTEGERS = ['TRUSTED_PROXY_HOPS'];

    private const DEFAULT_ACCESS_TOKEN_TTL = '900';
    private const DEFAULT_REFRESH_TOKEN_TTL = '604800';

    private const POSITIVE_INTEGER_PATTERN = '/^[1-9][0-9]*$/';
    private const NON_NEGATIVE_INTEGER_PATTERN = '/^[0-9]+$/';

    /**
     * Runs every check against the current environment.
     *
     * @return list<string> Failure lines, empty when the configuration is valid.
     */
    public function validate(): array
    {
        return [
            ...$this->checkSecretKey(),
            ...$this->checkPreviousSecretKeys(),
            ...$this->checkRequired(),
            ...$this->checkIntegers(),
            ...$this->checkTokenTtls(),
        ];
    }

    /**
     * Checks that `SECRET_KEY` is set and long enough.
     *
     * @return list<string>
     */
    private function checkSecretKey(): array
    {
        $key = Env::get('SECRET_KEY');

        if ($key === null) {
            return ['SECRET_KEY: missing'];
        }

        if (strlen($key) < self::SECRET_KEY_MIN_BYTES) {
            return ['SECRET_KEY: too short'];
        }

        return [];
    }

    /**
     * Checks every `PREVIOUS_SECRET_KEYS` entry, reporting each kind of failure once.
     *
     * @return list<string>
     */
    private function checkPreviousSecretKeys(): array
    {
        $keys = Env::get('PREVIOUS_SECRET_KEYS');

        if ($keys === null) {
            return [];
        }

        $failures = [];

        foreach (explode(',', $keys) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                $failures['empty'] = 'PREVIOUS_SECRET_KEYS: empty entry';
            } elseif (strlen($entry) < self::SECRET_KEY_MIN_BYTES) {
                $failures['short'] = 'PREVIOUS_SECRET_KEYS: entry too short';
            }
        }

        ksort($failures);

        return array_values($failures);
    }

    /**
     * Checks that every required setting is set.
     *
     * @return list<string>
     */
    private function checkRequired(): array
    {
        $failures = [];

        foreach (self::REQUIRED as $name) {
            if (Env::get($name) === null) {
                $failures[] = "{$name}: missing";
            }
        }

        return $failures;
    }

    /**
     * Checks the format of every integer setting that is set.
     *
     * @return list<string>
     */
    private function checkIntegers(): array
    {
        $failures = [];

        foreach (self::POSITIVE_INTEGERS as $name) {
            if (!$this->matchesWhenSet($name, self::POSITIVE_INTEGER_PATTERN)) {
                $failures[] = "{$name}: not a positive integer";
            }
        }

        foreach (self::NON_NEGATIVE_INTEGERS as $name) {
            if (!$this->matchesWhenSet($name, self::NON_NEGATIVE_INTEGER_PATTERN)) {
                $failures[] = "{$name}: not a non-negative integer";
            }
        }

        return $failures;
    }

    /**
     * Checks that `ACCESS_TOKEN_TTL` is less than `REFRESH_TOKEN_TTL`, when both are valid.
     *
     * @return list<string>
     */
    private function checkTokenTtls(): array
    {
        $access = (string) Env::get('ACCESS_TOKEN_TTL', self::DEFAULT_ACCESS_TOKEN_TTL);
        $refresh = (string) Env::get('REFRESH_TOKEN_TTL', self::DEFAULT_REFRESH_TOKEN_TTL);

        if (
            preg_match(self::POSITIVE_INTEGER_PATTERN, $access) !== 1
            || preg_match(self::POSITIVE_INTEGER_PATTERN, $refresh) !== 1
        ) {
            return [];
        }

        // Compared as digit strings so that values beyond PHP_INT_MAX stay exact.
        $comparison = strlen($access) <=> strlen($refresh) ?: strcmp($access, $refresh);

        if ($comparison < 0) {
            return [];
        }

        return ['ACCESS_TOKEN_TTL: not less than REFRESH_TOKEN_TTL'];
    }

    /**
     * Tells whether a setting is unset, empty, or matches the pattern.
     *
     * @param string $name    Name of the environment variable.
     * @param string $pattern Regular expression the value must match.
     *
     * @return boolean
     */
    private function matchesWhenSet(string $name, string $pattern): bool
    {
        $value = Env::get($name);

        return $value === null || preg_match($pattern, $value) === 1;
    }
}
