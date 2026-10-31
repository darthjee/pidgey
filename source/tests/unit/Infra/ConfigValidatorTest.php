<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\ConfigValidator;
use Pidgey\Infra\Env;

#[CoversClass(ConfigValidator::class)]
#[UsesClass(Env::class)]
final class ConfigValidatorTest extends TestCase
{
    private const VALID_KEY = 'valid-key-0123456789abcdef0123456789abcdef';
    private const OTHER_KEY = 'other-key-0123456789abcdef0123456789abcdef';

    private const VARIABLES = [
        'SECRET_KEY',
        'PREVIOUS_SECRET_KEYS',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
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

    public function testValidBaselinePasses(): void
    {
        $this->assertSame([], (new ConfigValidator())->validate());
    }

    public function testFullyConfiguredEnvironmentPasses(): void
    {
        putenv('PREVIOUS_SECRET_KEYS= ' . self::OTHER_KEY . ' , ' . self::VALID_KEY);
        putenv('DB_PORT=3306');
        putenv('ACCESS_TOKEN_TTL=60');
        putenv('REFRESH_TOKEN_TTL=61');
        putenv('TRUSTED_PROXY_HOPS=0');
        putenv('ACCOUNT_EDIT_MAX_ATTEMPTS=1');

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    public function testEmptyOptionalSettingsUseDefaults(): void
    {
        putenv('PREVIOUS_SECRET_KEYS=');
        putenv('ACCESS_TOKEN_TTL=');
        putenv('TRUSTED_PROXY_HOPS=');

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    /**
     * @return array<string, array{string, string|null, string}>
     */
    public static function singleFailures(): array
    {
        $cases = [
            'secret key unset' => ['SECRET_KEY', null, 'SECRET_KEY: missing'],
            'secret key empty' => ['SECRET_KEY', '', 'SECRET_KEY: missing'],
            'secret key 31 bytes' => ['SECRET_KEY', str_repeat('k', 31), 'SECRET_KEY: too short'],
            'previous key trailing comma' => [
                'PREVIOUS_SECRET_KEYS',
                self::OTHER_KEY . ',',
                'PREVIOUS_SECRET_KEYS: empty entry',
            ],
            'previous key blank entry' => [
                'PREVIOUS_SECRET_KEYS',
                self::OTHER_KEY . ',   ,' . self::VALID_KEY,
                'PREVIOUS_SECRET_KEYS: empty entry',
            ],
            'previous key short entry' => [
                'PREVIOUS_SECRET_KEYS',
                self::OTHER_KEY . ',short-previous-key',
                'PREVIOUS_SECRET_KEYS: entry too short',
            ],
            'db host unset' => ['DB_HOST', null, 'DB_HOST: missing'],
            'db name empty' => ['DB_NAME', '', 'DB_NAME: missing'],
            'db user unset' => ['DB_USER', null, 'DB_USER: missing'],
            'proxy hops negative' => ['TRUSTED_PROXY_HOPS', '-1', 'TRUSTED_PROXY_HOPS: not a non-negative integer'],
            'proxy hops text' => ['TRUSTED_PROXY_HOPS', 'one', 'TRUSTED_PROXY_HOPS: not a non-negative integer'],
            'access ttl equals refresh ttl' => [
                'ACCESS_TOKEN_TTL',
                '604800',
                'ACCESS_TOKEN_TTL: not less than REFRESH_TOKEN_TTL',
            ],
            'refresh ttl below access ttl' => [
                'REFRESH_TOKEN_TTL',
                '899',
                'ACCESS_TOKEN_TTL: not less than REFRESH_TOKEN_TTL',
            ],
            'access ttl beyond integer range' => [
                'ACCESS_TOKEN_TTL',
                '99999999999999999999999',
                'ACCESS_TOKEN_TTL: not less than REFRESH_TOKEN_TTL',
            ],
        ];

        $positive = [
            'DB_WAIT_TIMEOUT',
            'MIGRATE_LOCK_TIMEOUT',
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

        foreach ($positive as $name) {
            foreach (['0', '-5', '1.5', 'abc', '007', ' 5x'] as $value) {
                $cases["{$name} {$value}"] = [$name, $value, "{$name}: not a positive integer"];
            }
        }

        // The TTL comparison only runs when both values are valid, so these report one failure.
        foreach (['ACCESS_TOKEN_TTL', 'REFRESH_TOKEN_TTL'] as $name) {
            foreach (['0', 'abc'] as $value) {
                $cases["{$name} {$value}"] = [$name, $value, "{$name}: not a positive integer"];
            }
        }

        return $cases;
    }

    #[DataProvider('singleFailures')]
    public function testSingleRuleFails(string $name, ?string $value, string $expected): void
    {
        putenv($value === null ? $name : "{$name}={$value}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame([$expected], $failures);
        $this->assertNoValueLeaks($failures, $value);
    }

    public function testPreviousKeyFailuresAreReportedOnceEach(): void
    {
        putenv('PREVIOUS_SECRET_KEYS=short-one,,short-two,');

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['PREVIOUS_SECRET_KEYS: empty entry', 'PREVIOUS_SECRET_KEYS: entry too short'], $failures);
        $this->assertNoValueLeaks($failures, 'short-one');
        $this->assertNoValueLeaks($failures, 'short-two');
    }

    public function testDbPortIsNotChecked(): void
    {
        putenv('DB_PORT=not-a-port');

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    public function testSeveralFailuresAreAllReported(): void
    {
        putenv('SECRET_KEY=short-secret');
        putenv('PREVIOUS_SECRET_KEYS=,');
        putenv('DB_HOST');
        putenv('DB_NAME');
        putenv('DB_USER');
        putenv('DB_WAIT_TIMEOUT=zero');
        putenv('TRUSTED_PROXY_HOPS=-2');
        putenv('ACCESS_TOKEN_TTL=100');
        putenv('REFRESH_TOKEN_TTL=50');

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(
            [
                'SECRET_KEY: too short',
                'PREVIOUS_SECRET_KEYS: empty entry',
                'DB_HOST: missing',
                'DB_NAME: missing',
                'DB_USER: missing',
                'DB_WAIT_TIMEOUT: not a positive integer',
                'TRUSTED_PROXY_HOPS: not a non-negative integer',
                'ACCESS_TOKEN_TTL: not less than REFRESH_TOKEN_TTL',
            ],
            $failures,
        );
        $this->assertNoValueLeaks($failures, 'short-secret');
        $this->assertNoValueLeaks($failures, 'zero');
    }

    /**
     * @param list<string> $failures
     */
    private function assertNoValueLeaks(array $failures, ?string $value): void
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
