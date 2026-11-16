<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra\ConfigValidator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\ConfigValidator;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\OriginParser;
use Pidgey\Tests\Support\ConfigValidatorTestCase;

#[CoversClass(ConfigValidator::class)]
#[UsesClass(AppPath::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(Env::class)]
#[UsesClass(LoggerFactory::class)]
#[UsesClass(OriginParser::class)]
final class ConfigValidatorGeneralTest extends ConfigValidatorTestCase
{
    public function testValidBaselinePasses(): void
    {
        $this->assertSame([], (new ConfigValidator())->validate());
    }

    public function testFullyConfiguredEnvironmentPasses(): void
    {
        putenv('PREVIOUS_SECRET_KEYS= ' . self::OTHER_KEY . ' , ' . self::VALID_KEY);
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS= ' . self::PREVIOUS_SMTP_KEY . ' , ' . self::OLDER_SMTP_KEY);
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
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS=');
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
            'smtp key unset' => ['SMTP_ENCRYPTION_KEY', null, 'SMTP_ENCRYPTION_KEY: missing'],
            'smtp key empty' => ['SMTP_ENCRYPTION_KEY', '', 'SMTP_ENCRYPTION_KEY: missing'],
            'smtp key 31 bytes' => ['SMTP_ENCRYPTION_KEY', str_repeat('s', 31), 'SMTP_ENCRYPTION_KEY: too short'],
            'smtp key same as secret key' => [
                'SMTP_ENCRYPTION_KEY',
                self::VALID_KEY,
                'SMTP_ENCRYPTION_KEY: same as SECRET_KEY',
            ],
            'previous smtp key trailing comma' => [
                'PREVIOUS_SMTP_ENCRYPTION_KEYS',
                self::PREVIOUS_SMTP_KEY . ',',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: empty entry',
            ],
            'previous smtp key blank entry' => [
                'PREVIOUS_SMTP_ENCRYPTION_KEYS',
                self::PREVIOUS_SMTP_KEY . ',  ,' . self::OLDER_SMTP_KEY,
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: empty entry',
            ],
            'previous smtp key short entry' => [
                'PREVIOUS_SMTP_ENCRYPTION_KEYS',
                self::PREVIOUS_SMTP_KEY . ',short-previous-smtp-key',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: too short',
            ],
            'previous smtp key same as secret key' => [
                'PREVIOUS_SMTP_ENCRYPTION_KEYS',
                self::PREVIOUS_SMTP_KEY . ', ' . self::VALID_KEY,
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: same as SECRET_KEY',
            ],
            'previous smtp key is the current key' => [
                'PREVIOUS_SMTP_ENCRYPTION_KEYS',
                ' ' . self::SMTP_KEY . ' ',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: duplicate key',
            ],
            'previous smtp key listed twice' => [
                'PREVIOUS_SMTP_ENCRYPTION_KEYS',
                self::PREVIOUS_SMTP_KEY . ',' . self::OLDER_SMTP_KEY . ', ' . self::PREVIOUS_SMTP_KEY,
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: duplicate key',
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
            'SMTP_TEST_MAX_ATTEMPTS',
            'SMTP_TEST_WINDOW',
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

        foreach (explode(',', (string) $value) as $entry) {
            $this->assertNoValueLeaks($failures, $entry);
        }
    }

    /**
     * @return array<string, array{string|null, string|null, list<string>}>
     */
    public static function migrationUserPairings(): array
    {
        return [
            'neither set' => [null, null, []],
            'both set' => ['distinctive_migrator', 'distinctive-migrate-secret', []],
            'user without password' => [
                'distinctive_migrator',
                null,
                ['DB_MIGRATE_PASSWORD: missing'],
            ],
            'user with empty password' => [
                'distinctive_migrator',
                '',
                ['DB_MIGRATE_PASSWORD: missing'],
            ],
            'password without user' => [
                null,
                'distinctive-migrate-secret',
                ['DB_MIGRATE_USER: missing'],
            ],
            'password with empty user' => [
                '',
                'distinctive-migrate-secret',
                ['DB_MIGRATE_USER: missing'],
            ],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('migrationUserPairings')]
    public function testMigrationUserAndPasswordAreSetTogether(
        ?string $user,
        ?string $password,
        array $expected,
    ): void {
        putenv($user === null ? 'DB_MIGRATE_USER' : "DB_MIGRATE_USER={$user}");
        putenv($password === null ? 'DB_MIGRATE_PASSWORD' : "DB_MIGRATE_PASSWORD={$password}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame($expected, $failures);
        $this->assertNoValueLeaks($failures, $user);
        $this->assertNoValueLeaks($failures, $password);
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
}
