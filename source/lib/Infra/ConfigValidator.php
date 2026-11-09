<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use Pidgey\Crypto\EncryptionKeys;
use SensitiveParameter;

/**
 * Checks every configuration rule that refuses boot (see `architecture/configuration.md`).
 *
 * Failures are reported as `<CHECK>: <reason>` lines that name the setting and never include
 * its value. Unset or empty optional settings fall back to their defaults and pass.
 */
final class ConfigValidator
{
    public const SECRET_KEY_MIN_BYTES = 32;

    /** The shipped development key, refused outside development and test. */
    public const DEVELOPMENT_SECRET_KEY = 'pidgey-development-only-insecure-secret-key-0000';

    /** The shipped development SMTP encryption key, refused outside development and test. */
    public const DEVELOPMENT_SMTP_ENCRYPTION_KEY = 'pidgey-development-only-insecure-smtp-key-0000000';

    /** Default `ACCESS_TOKEN_TTL`, in seconds. */
    public const DEFAULT_ACCESS_TOKEN_TTL = 900;

    /** Default `REFRESH_TOKEN_TTL`, in seconds. */
    public const DEFAULT_REFRESH_TOKEN_TTL = 604800;

    /** Default `ACCOUNT_EDIT_MAX_ATTEMPTS`: failed account edits before locking. */
    public const DEFAULT_ACCOUNT_EDIT_MAX_ATTEMPTS = 5;

    /** Default `ACCOUNT_EDIT_LOCK_TTL`, in seconds. */
    public const DEFAULT_ACCOUNT_EDIT_LOCK_TTL = 300;

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

    /** PHP_ENV values that are not production; any other value, or none, counts as production. */
    public const NON_PRODUCTION_ENVIRONMENTS = ['development', 'test'];

    private const POSITIVE_INTEGER_PATTERN = '/^[1-9][0-9]*$/';
    private const NON_NEGATIVE_INTEGER_PATTERN = '/^[0-9]+$/';

    /** `PREVIOUS_SMTP_ENCRYPTION_KEYS` failure reasons, in reporting order. */
    private const PREVIOUS_SMTP_KEY_REASONS = [
        'empty entry',
        'too short',
        'development key',
        'same as SECRET_KEY',
        'duplicate key',
    ];

    private readonly bool $sodiumLoaded;

    /**
     * Builds the validator.
     *
     * @param boolean|null $sodiumLoaded Whether the `sodium` extension is loaded; defaults to
     *                                   `extension_loaded('sodium')`.
     */
    public function __construct(?bool $sodiumLoaded = null)
    {
        $this->sodiumLoaded = $sodiumLoaded ?? extension_loaded('sodium');
    }

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
            ...$this->checkSmtpEncryptionKey(),
            ...$this->checkPreviousSmtpEncryptionKeys(),
            ...$this->checkRequired(),
            ...$this->checkMigrationUser(),
            ...$this->checkIntegers(),
            ...$this->checkTokenTtls(),
            ...$this->checkAllowedOrigins(),
            ...$this->checkLogLevel(),
            ...$this->checkDevelopmentKey(),
            ...$this->checkSodium(),
        ];
    }

    /**
     * Runs only the SMTP encryption key rules and the `sodium` check, for the commands that use
     * the keys without serving requests (`bin/reencrypt-smtp-passwords`).
     *
     * The rules are the same ones `validate()` runs, with the same failure lines.
     *
     * @return list<string> Failure lines, empty when the keys are usable.
     */
    public function validateSmtpEncryption(): array
    {
        return [
            ...$this->checkSmtpEncryptionKey(),
            ...$this->checkPreviousSmtpEncryptionKeys(),
            ...$this->checkSodium(),
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
     * Checks that `SMTP_ENCRYPTION_KEY` is set, long enough, not the development key outside
     * development and test, and not `SECRET_KEY`.
     *
     * @return list<string>
     */
    private function checkSmtpEncryptionKey(): array
    {
        $key = Env::get('SMTP_ENCRYPTION_KEY');

        if ($key === null) {
            return ['SMTP_ENCRYPTION_KEY: missing'];
        }

        if (strlen($key) < self::SECRET_KEY_MIN_BYTES) {
            return ['SMTP_ENCRYPTION_KEY: too short'];
        }

        $failures = [];

        if ($key === self::DEVELOPMENT_SMTP_ENCRYPTION_KEY && !$this->isDevelopmentOrTest()) {
            $failures[] = 'SMTP_ENCRYPTION_KEY: development key';
        }

        if ($key === Env::get('SECRET_KEY')) {
            $failures[] = 'SMTP_ENCRYPTION_KEY: same as SECRET_KEY';
        }

        return $failures;
    }

    /**
     * Checks every `PREVIOUS_SMTP_ENCRYPTION_KEYS` entry, reporting each kind of failure once.
     *
     * Two configured SMTP keys (the current key and the previous keys) sharing a key id are a
     * `duplicate key`.
     *
     * @return list<string>
     */
    private function checkPreviousSmtpEncryptionKeys(): array
    {
        $keys = Env::get('PREVIOUS_SMTP_ENCRYPTION_KEYS');

        if ($keys === null) {
            return [];
        }

        $current = Env::get('SMTP_ENCRYPTION_KEY');
        $seenIds = $current === null ? [] : [EncryptionKeys::keyIdOf($current) => true];
        $reasons = [];

        foreach (explode(',', $keys) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                $reasons['empty entry'] = true;

                continue;
            }

            $reasons += $this->previousSmtpKeyReasons($entry);
            $keyId = EncryptionKeys::keyIdOf($entry);

            if (isset($seenIds[$keyId])) {
                $reasons['duplicate key'] = true;
            }

            $seenIds[$keyId] = true;
        }

        $failures = [];

        foreach (self::PREVIOUS_SMTP_KEY_REASONS as $reason) {
            if (isset($reasons[$reason])) {
                $failures[] = "PREVIOUS_SMTP_ENCRYPTION_KEYS: {$reason}";
            }
        }

        return $failures;
    }

    /**
     * Returns the length, development-key and `SECRET_KEY` reasons of one non-empty previous key.
     *
     * @param string $entry The trimmed entry.
     *
     * @return array<string, true>
     */
    private function previousSmtpKeyReasons(#[SensitiveParameter] string $entry): array
    {
        $reasons = [];

        if (strlen($entry) < self::SECRET_KEY_MIN_BYTES) {
            $reasons['too short'] = true;
        }

        if ($entry === self::DEVELOPMENT_SMTP_ENCRYPTION_KEY && !$this->isDevelopmentOrTest()) {
            $reasons['development key'] = true;
        }

        if ($entry === Env::get('SECRET_KEY')) {
            $reasons['same as SECRET_KEY'] = true;
        }

        return $reasons;
    }

    /**
     * Checks that the `sodium` extension, needed to encrypt the SMTP passwords, is loaded.
     *
     * @return list<string>
     */
    private function checkSodium(): array
    {
        return $this->sodiumLoaded ? [] : ['PHP extension: sodium missing'];
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
     * Checks that `DB_MIGRATE_USER` and `DB_MIGRATE_PASSWORD` are set together, or not at all.
     *
     * @return list<string>
     */
    private function checkMigrationUser(): array
    {
        $hasUser = Env::get('DB_MIGRATE_USER') !== null;
        $hasPassword = Env::get('DB_MIGRATE_PASSWORD') !== null;

        if ($hasUser && !$hasPassword) {
            return ['DB_MIGRATE_PASSWORD: missing'];
        }

        if ($hasPassword && !$hasUser) {
            return ['DB_MIGRATE_USER: missing'];
        }

        return [];
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
        $access = (string) Env::get('ACCESS_TOKEN_TTL', (string) self::DEFAULT_ACCESS_TOKEN_TTL);
        $refresh = (string) Env::get('REFRESH_TOKEN_TTL', (string) self::DEFAULT_REFRESH_TOKEN_TTL);

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
     * Checks every `ALLOWED_ORIGINS` entry, reporting each kind of failure once.
     *
     * Entries must be bare http/https origins, and https unless PHP_ENV is development or test.
     *
     * @return list<string>
     */
    private function checkAllowedOrigins(): array
    {
        $origins = Env::get('ALLOWED_ORIGINS');

        if ($origins === null) {
            return [];
        }

        $failures = [];

        foreach (explode(',', $origins) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            $origin = OriginParser::parseOrigin($entry);

            if ($origin === null) {
                $failures['invalid'] = 'ALLOWED_ORIGINS: invalid origin';
            } elseif ($origin['scheme'] === 'http' && !$this->isDevelopmentOrTest()) {
                $failures['not https'] = 'ALLOWED_ORIGINS: not https';
            }
        }

        ksort($failures);

        return array_values($failures);
    }

    /**
     * Checks that `LOG_LEVEL`, when set, is one of the logger's levels (exact, lower case).
     *
     * @return list<string>
     */
    private function checkLogLevel(): array
    {
        $level = Env::get('LOG_LEVEL');

        if ($level === null || in_array($level, LoggerFactory::LEVELS, true)) {
            return [];
        }

        return ['LOG_LEVEL: invalid level'];
    }

    /**
     * Checks that the development key is used neither as `SECRET_KEY` nor as a
     * `PREVIOUS_SECRET_KEYS` entry, unless PHP_ENV is development or test.
     *
     * @return list<string>
     */
    private function checkDevelopmentKey(): array
    {
        if ($this->isDevelopmentOrTest()) {
            return [];
        }

        $failures = [];

        if (Env::get('SECRET_KEY') === self::DEVELOPMENT_SECRET_KEY) {
            $failures[] = 'SECRET_KEY: development key';
        }

        $previous = explode(',', (string) Env::get('PREVIOUS_SECRET_KEYS', ''));

        if (in_array(self::DEVELOPMENT_SECRET_KEY, array_map('trim', $previous), true)) {
            $failures[] = 'PREVIOUS_SECRET_KEYS: development key';
        }

        return $failures;
    }

    /**
     * Tells whether PHP_ENV is exactly `development` or `test`; anything else counts as production.
     *
     * @return boolean
     */
    private function isDevelopmentOrTest(): bool
    {
        return in_array(Env::get('PHP_ENV'), self::NON_PRODUCTION_ENVIRONMENTS, true);
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
