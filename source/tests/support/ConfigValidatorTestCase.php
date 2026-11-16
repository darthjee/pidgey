<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Shared environment handling, helpers and providers for the ConfigValidator tests.
 */
abstract class ConfigValidatorTestCase extends TestCase
{
    protected const VALID_KEY = 'test-secret-value-not-a-real-secret-valid';
    protected const OTHER_KEY = 'test-secret-value-not-a-real-secret-other';
    protected const SMTP_KEY = 'test-smtp-encryption-value-not-a-real-key-current';
    protected const PREVIOUS_SMTP_KEY = 'test-smtp-encryption-value-not-a-real-key-previous';
    protected const OLDER_SMTP_KEY = 'test-smtp-encryption-value-not-a-real-key-older';

    protected const VARIABLES = [
        'SECRET_KEY',
        'PREVIOUS_SECRET_KEYS',
        'SMTP_ENCRYPTION_KEY',
        'PREVIOUS_SMTP_ENCRYPTION_KEYS',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'DB_MIGRATE_USER',
        'DB_MIGRATE_PASSWORD',
        'DB_WAIT_TIMEOUT',
        'MIGRATE_LOCK_TIMEOUT',
        'ACCESS_TOKEN_TTL',
        'REFRESH_TOKEN_TTL',
        'TRUSTED_PROXY_HOPS',
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
        'ALLOWED_ORIGINS',
        'LOG_LEVEL',
        'PHP_ENV',
        'SMTP_CONNECT_TIMEOUT',
        'SMTP_COMMAND_TIMEOUT',
        'SMTP_TOTAL_TIMEOUT',
        'SMTP_TEST_MAX_ATTEMPTS',
        'SMTP_TEST_WINDOW',
        'SMTP_CA_FILE',
        'SMTP_EHLO_NAME',
    ];

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
            putenv($name);
        }

        putenv('SECRET_KEY=' . self::VALID_KEY);
        putenv('SMTP_ENCRYPTION_KEY=' . self::SMTP_KEY);
        putenv('DB_HOST=db.example');
        putenv('DB_NAME=pidgey');
        putenv('DB_USER=pidgey');
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function productionEnvironments(): array
    {
        return [
            'unset' => [null],
            'empty' => [''],
            'production' => ['production'],
            'other case' => ['Production'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function developmentEnvironments(): array
    {
        return [
            'development' => ['development'],
            'test' => ['test'],
        ];
    }

    /**
     * @param list<string> $failures
     */
    protected function assertNoOriginLeaks(array $failures, string $value): void
    {
        foreach (explode(',', $value) as $entry) {
            $this->assertNoValueLeaks($failures, $entry);
            $this->assertNoValueLeaks($failures, (string) preg_replace('#^[a-z]+://#', '', trim($entry)));
        }
    }

    /**
     * @param list<string> $failures
     */
    protected function assertNoValueLeaks(array $failures, ?string $value): void
    {
        $value = trim((string) $value);

        // Single characters and digits can occur in the fixed check names and reasons.
        if (strlen($value) < 3) {
            return;
        }

        foreach ($failures as $failure) {
            $this->assertStringNotContainsString($value, $failure);
        }
    }
}
