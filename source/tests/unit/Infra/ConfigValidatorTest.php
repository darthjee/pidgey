<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\ConfigValidator;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\OriginParser;

#[CoversClass(ConfigValidator::class)]
#[UsesClass(AppPath::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(Env::class)]
#[UsesClass(LoggerFactory::class)]
#[UsesClass(OriginParser::class)]
final class ConfigValidatorTest extends TestCase
{
    private const VALID_KEY = 'test-secret-value-not-a-real-secret-valid';
    private const OTHER_KEY = 'test-secret-value-not-a-real-secret-other';
    private const SMTP_KEY = 'test-smtp-encryption-value-not-a-real-key-current';
    private const PREVIOUS_SMTP_KEY = 'test-smtp-encryption-value-not-a-real-key-previous';
    private const OLDER_SMTP_KEY = 'test-smtp-encryption-value-not-a-real-key-older';

    private const VARIABLES = [
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

    public function testPreviousKeyFailuresAreReportedOnceEach(): void
    {
        putenv('PREVIOUS_SECRET_KEYS=short-one,,short-two,');

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['PREVIOUS_SECRET_KEYS: empty entry', 'PREVIOUS_SECRET_KEYS: entry too short'], $failures);
        $this->assertNoValueLeaks($failures, 'short-one');
        $this->assertNoValueLeaks($failures, 'short-two');
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function validLogLevels(): array
    {
        $cases = ['unset' => [null], 'empty' => ['']];

        foreach (LoggerFactory::LEVELS as $level) {
            $cases[$level] = [$level];
        }

        return $cases;
    }

    #[DataProvider('validLogLevels')]
    public function testValidLogLevelsPass(?string $value): void
    {
        putenv($value === null ? 'LOG_LEVEL' : "LOG_LEVEL={$value}");

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLogLevels(): array
    {
        return [
            'upper case' => ['INFO'],
            'leading space' => [' info'],
            'unknown name' => ['warn'],
        ];
    }

    #[DataProvider('invalidLogLevels')]
    public function testInvalidLogLevelFailsWithoutTheValue(string $value): void
    {
        putenv("LOG_LEVEL={$value}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['LOG_LEVEL: invalid level'], $failures);

        foreach ($failures as $failure) {
            $this->assertStringNotContainsString($value, $failure);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validAllowedOrigins(): array
    {
        return [
            'single https' => ['https://admin.example.com'],
            'several with port' => ['https://admin.example.com, https://other.example:8443'],
            'ipv6' => ['https://[::1]:8443'],
            'blank entries' => [' , https://admin.example.com ,,'],
            'only blank entries' => [' , '],
            'empty' => [''],
        ];
    }

    #[DataProvider('validAllowedOrigins')]
    public function testValidAllowedOriginsPass(string $value): void
    {
        putenv('ALLOWED_ORIGINS=' . $value);

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAllowedOrigins(): array
    {
        return [
            'path' => ['https://admin.example.com/admin'],
            'trailing slash' => ['https://admin.example.com/'],
            'query' => ['https://admin.example.com?x=1'],
            'fragment' => ['https://admin.example.com#x'],
            'user info' => ['https://user@admin.example.com'],
            'no scheme' => ['admin.example.com'],
            'ftp scheme' => ['ftp://admin.example.com'],
            'wildcard' => ['*'],
            'null' => ['null'],
            'port 0' => ['https://admin.example.com:0'],
            'one bad among good' => ['https://good.example,https://admin.example.com/'],
        ];
    }

    #[DataProvider('invalidAllowedOrigins')]
    public function testInvalidAllowedOriginsFail(string $value): void
    {
        putenv('ALLOWED_ORIGINS=' . $value);

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['ALLOWED_ORIGINS: invalid origin'], $failures);
        $this->assertNoOriginLeaks($failures, $value);
    }

    /**
     * @return array<string, array{string|null, list<string>}>
     */
    public static function httpAllowedOrigins(): array
    {
        return [
            'production' => ['production', ['ALLOWED_ORIGINS: not https']],
            'unset' => [null, ['ALLOWED_ORIGINS: not https']],
            'empty' => ['', ['ALLOWED_ORIGINS: not https']],
            'other case' => ['Development', ['ALLOWED_ORIGINS: not https']],
            'staging' => ['staging', ['ALLOWED_ORIGINS: not https']],
            'development' => ['development', []],
            'test' => ['test', []],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('httpAllowedOrigins')]
    public function testHttpAllowedOriginsNeedADevelopmentEnvironment(?string $environment, array $expected): void
    {
        putenv('ALLOWED_ORIGINS=http://localhost:5173');
        putenv($environment === null ? 'PHP_ENV' : "PHP_ENV={$environment}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame($expected, $failures);
        $this->assertNoOriginLeaks($failures, 'http://localhost:5173');
    }

    public function testAllowedOriginFailuresAreReportedOnceEach(): void
    {
        $value = 'http://one.example,https://two.example/,http://three.example,ftp://four.example';
        putenv('ALLOWED_ORIGINS=' . $value);
        putenv('PHP_ENV=production');

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['ALLOWED_ORIGINS: invalid origin', 'ALLOWED_ORIGINS: not https'], $failures);
        $this->assertNoOriginLeaks($failures, $value);
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

    #[DataProvider('productionEnvironments')]
    public function testDevelopmentKeyAsSecretKeyIsRefusedInProduction(?string $environment): void
    {
        putenv('SECRET_KEY=' . ConfigValidator::DEVELOPMENT_SECRET_KEY);
        putenv($environment === null ? 'PHP_ENV' : "PHP_ENV={$environment}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['SECRET_KEY: development key'], $failures);
        $this->assertNoValueLeaks($failures, ConfigValidator::DEVELOPMENT_SECRET_KEY);
    }

    #[DataProvider('productionEnvironments')]
    public function testDevelopmentKeyAsPreviousKeyIsRefusedOnceInProduction(?string $environment): void
    {
        $development = ConfigValidator::DEVELOPMENT_SECRET_KEY;
        putenv("PREVIOUS_SECRET_KEYS=  {$development} ," . self::OTHER_KEY . ",{$development}  ");
        putenv($environment === null ? 'PHP_ENV' : "PHP_ENV={$environment}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['PREVIOUS_SECRET_KEYS: development key'], $failures);
        $this->assertNoValueLeaks($failures, $development);
    }

    public function testDevelopmentKeyInBothSettingsIsReportedForEach(): void
    {
        putenv('SECRET_KEY=' . ConfigValidator::DEVELOPMENT_SECRET_KEY);
        putenv('PREVIOUS_SECRET_KEYS=' . ConfigValidator::DEVELOPMENT_SECRET_KEY);
        putenv('PHP_ENV=production');

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['SECRET_KEY: development key', 'PREVIOUS_SECRET_KEYS: development key'], $failures);
        $this->assertNoValueLeaks($failures, ConfigValidator::DEVELOPMENT_SECRET_KEY);
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

    #[DataProvider('developmentEnvironments')]
    public function testDevelopmentKeyIsAllowedInDevelopmentAndTest(string $environment): void
    {
        putenv('SECRET_KEY=' . ConfigValidator::DEVELOPMENT_SECRET_KEY);
        putenv('PREVIOUS_SECRET_KEYS=' . ConfigValidator::DEVELOPMENT_SECRET_KEY);
        putenv("PHP_ENV={$environment}");

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    public function testDevelopmentKeyIsMatchedExactly(): void
    {
        $sameLength = 'pidgey-development-only-insecure-secret-key-0001';
        $this->assertSame(strlen(ConfigValidator::DEVELOPMENT_SECRET_KEY), strlen($sameLength));
        $this->assertSame(48, strlen(ConfigValidator::DEVELOPMENT_SECRET_KEY));
        putenv('SECRET_KEY=' . $sameLength);
        putenv('PREVIOUS_SECRET_KEYS=' . $sameLength);
        putenv('PHP_ENV=production');

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    public function testPreviousSmtpKeyFailuresAreReportedOnceEach(): void
    {
        $development = ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY;
        $entries = [
            'short-one',
            '',
            'short-two',
            $development,
            self::VALID_KEY,
            self::SMTP_KEY,
            self::PREVIOUS_SMTP_KEY,
            self::PREVIOUS_SMTP_KEY,
            $development,
            self::VALID_KEY,
            '',
        ];
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS=' . implode(',', $entries));
        putenv('PHP_ENV=production');

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(
            [
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: empty entry',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: too short',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: development key',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: same as SECRET_KEY',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: duplicate key',
            ],
            $failures,
        );

        foreach ($entries as $entry) {
            $this->assertNoValueLeaks($failures, $entry);
        }
    }

    public function testSmtpKeyCheckedAgainstAMissingSecretKeyOnlyReportsTheSecretKey(): void
    {
        putenv('SECRET_KEY');
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS=' . self::PREVIOUS_SMTP_KEY);

        $this->assertSame(['SECRET_KEY: missing'], (new ConfigValidator())->validate());
    }

    public function testPreviousSmtpKeysWithoutACurrentKeyOnlyReportTheMissingKey(): void
    {
        putenv('SMTP_ENCRYPTION_KEY');
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS=' . self::PREVIOUS_SMTP_KEY . ',' . self::OLDER_SMTP_KEY);

        $this->assertSame(['SMTP_ENCRYPTION_KEY: missing'], (new ConfigValidator())->validate());
    }

    #[DataProvider('productionEnvironments')]
    public function testDevelopmentSmtpKeyIsRefusedInProduction(?string $environment): void
    {
        putenv('SMTP_ENCRYPTION_KEY=' . ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY);
        putenv($environment === null ? 'PHP_ENV' : "PHP_ENV={$environment}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['SMTP_ENCRYPTION_KEY: development key'], $failures);
        $this->assertNoValueLeaks($failures, ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY);
    }

    #[DataProvider('productionEnvironments')]
    public function testDevelopmentSmtpKeyAsPreviousKeyIsRefusedOnceInProduction(?string $environment): void
    {
        $development = ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY;
        putenv("PREVIOUS_SMTP_ENCRYPTION_KEYS=  {$development} ," . self::PREVIOUS_SMTP_KEY);
        putenv($environment === null ? 'PHP_ENV' : "PHP_ENV={$environment}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['PREVIOUS_SMTP_ENCRYPTION_KEYS: development key'], $failures);
        $this->assertNoValueLeaks($failures, $development);
    }

    #[DataProvider('developmentEnvironments')]
    public function testDevelopmentSmtpKeyIsAllowedInDevelopmentAndTest(string $environment): void
    {
        putenv('SMTP_ENCRYPTION_KEY=' . ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY);
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS=' . self::PREVIOUS_SMTP_KEY);
        putenv("PHP_ENV={$environment}");

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    public function testDevelopmentSmtpKeyIsTheSharedValue(): void
    {
        $this->assertSame(
            'pidgey-development-only-insecure-smtp-key-0000000',
            ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY,
        );
        $this->assertSame(49, strlen(ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY));
    }

    public function testDevelopmentSmtpKeyIsMatchedExactly(): void
    {
        $sameLength = 'pidgey-development-only-insecure-smtp-key-0000001';
        $this->assertSame(strlen(ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY), strlen($sameLength));
        putenv('SMTP_ENCRYPTION_KEY=' . $sameLength);
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS=' . self::PREVIOUS_SMTP_KEY);
        putenv('PHP_ENV=production');

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    public function testMissingSodiumExtensionFails(): void
    {
        $this->assertSame(['PHP extension: sodium missing'], (new ConfigValidator(false))->validate());
    }

    public function testLoadedSodiumExtensionPasses(): void
    {
        $this->assertSame([], (new ConfigValidator(true))->validate());
    }

    public function testDefaultsToTheRunningSodiumExtension(): void
    {
        $expected = extension_loaded('sodium') ? [] : ['PHP extension: sodium missing'];

        $this->assertSame($expected, (new ConfigValidator())->validate());
    }

    public function testSmtpEncryptionValidationPassesWithoutTheOtherSettings(): void
    {
        foreach (['SECRET_KEY', 'DB_HOST', 'DB_NAME', 'DB_USER'] as $name) {
            putenv($name);
        }

        putenv('ACCESS_TOKEN_TTL=not-a-number');
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS=' . self::PREVIOUS_SMTP_KEY);

        $this->assertSame([], (new ConfigValidator(true))->validateSmtpEncryption());
    }

    public function testSmtpEncryptionValidationRunsTheSmtpKeyAndSodiumRules(): void
    {
        $development = ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY;
        putenv('PHP_ENV=production');
        putenv('SMTP_ENCRYPTION_KEY=' . $development);
        putenv("PREVIOUS_SMTP_ENCRYPTION_KEYS={$development},short," . self::VALID_KEY);
        putenv('SECRET_KEY=' . self::VALID_KEY);

        $failures = (new ConfigValidator(false))->validateSmtpEncryption();

        $this->assertSame(
            [
                'SMTP_ENCRYPTION_KEY: development key',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: too short',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: development key',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: same as SECRET_KEY',
                'PREVIOUS_SMTP_ENCRYPTION_KEYS: duplicate key',
                'PHP extension: sodium missing',
            ],
            $failures,
        );
        $this->assertNoValueLeaks($failures, $development);
    }

    public function testSmtpEncryptionValidationReportsAMissingKey(): void
    {
        putenv('SMTP_ENCRYPTION_KEY');

        $this->assertSame(['SMTP_ENCRYPTION_KEY: missing'], (new ConfigValidator(true))->validateSmtpEncryption());
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

    public function testSmtpDefaultsPass(): void
    {
        $this->assertSame(10, ConfigValidator::DEFAULT_SMTP_CONNECT_TIMEOUT);
        $this->assertSame(10, ConfigValidator::DEFAULT_SMTP_COMMAND_TIMEOUT);
        $this->assertSame(25, ConfigValidator::DEFAULT_SMTP_TOTAL_TIMEOUT);
        $this->assertSame([], (new ConfigValidator())->validate());
    }

    public function testValidSmtpSettingsPass(): void
    {
        putenv('SMTP_CONNECT_TIMEOUT=5');
        putenv('SMTP_COMMAND_TIMEOUT=30');
        putenv('SMTP_TOTAL_TIMEOUT=30');
        putenv('SMTP_CA_FILE=tests/fixtures/tls/ca.pem');
        putenv('SMTP_EHLO_NAME=mail.example.com');

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidSmtpTimeouts(): array
    {
        $cases = [];

        foreach (['SMTP_CONNECT_TIMEOUT', 'SMTP_COMMAND_TIMEOUT', 'SMTP_TOTAL_TIMEOUT'] as $name) {
            $values = ['zero' => '0', 'negative' => '-5', 'decimal' => '2.5', 'word' => 'distinctive-timeout'];

            foreach ($values as $kind => $value) {
                $cases["{$name} {$kind}"] = [$name, $value];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidSmtpTimeouts')]
    public function testInvalidSmtpTimeoutOnlyReportsTheFormat(string $name, string $value): void
    {
        putenv("{$name}={$value}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(["{$name}: not a positive integer"], $failures);
        $this->assertNoValueLeaks($failures, $value);
    }

    /**
     * @return array<string, array{array<string, string>, list<string>}>
     */
    public static function smtpTimeoutComparisons(): array
    {
        return [
            'total alone below the defaults' => [
                ['SMTP_TOTAL_TIMEOUT' => '5'],
                [
                    'SMTP_CONNECT_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT',
                    'SMTP_COMMAND_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT',
                ],
            ],
            'connect above the default total' => [
                ['SMTP_CONNECT_TIMEOUT' => '26'],
                ['SMTP_CONNECT_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT'],
            ],
            'command above the total' => [
                ['SMTP_COMMAND_TIMEOUT' => '31', 'SMTP_TOTAL_TIMEOUT' => '30'],
                ['SMTP_COMMAND_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT'],
            ],
            'equal values pass' => [
                ['SMTP_CONNECT_TIMEOUT' => '25', 'SMTP_COMMAND_TIMEOUT' => '25'],
                [],
            ],
            'huge values compared exactly' => [
                [
                    'SMTP_CONNECT_TIMEOUT' => '99999999999999999999',
                    'SMTP_TOTAL_TIMEOUT' => '99999999999999999998',
                ],
                ['SMTP_CONNECT_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT'],
            ],
            'invalid connect skips its comparison' => [
                ['SMTP_CONNECT_TIMEOUT' => 'x', 'SMTP_TOTAL_TIMEOUT' => '5'],
                [
                    'SMTP_CONNECT_TIMEOUT: not a positive integer',
                    'SMTP_COMMAND_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT',
                ],
            ],
            'invalid total skips both comparisons' => [
                [
                    'SMTP_CONNECT_TIMEOUT' => '100',
                    'SMTP_COMMAND_TIMEOUT' => '100',
                    'SMTP_TOTAL_TIMEOUT' => '0',
                ],
                ['SMTP_TOTAL_TIMEOUT: not a positive integer'],
            ],
        ];
    }

    /**
     * @param array<string, string> $settings
     * @param list<string>          $expected
     */
    #[DataProvider('smtpTimeoutComparisons')]
    public function testSmtpTimeoutsDoNotExceedTheTotal(array $settings, array $expected): void
    {
        foreach ($settings as $name => $value) {
            putenv("{$name}={$value}");
        }

        $this->assertSame($expected, (new ConfigValidator())->validate());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function readableCaFiles(): array
    {
        return [
            'relative' => ['tests/fixtures/tls/ca.pem'],
            'absolute' => [dirname(__DIR__, 3) . '/tests/fixtures/tls/ca.pem'],
        ];
    }

    #[DataProvider('readableCaFiles')]
    public function testReadableCaFilePasses(string $path): void
    {
        putenv("SMTP_CA_FILE={$path}");

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableCaFiles(): array
    {
        return [
            'missing relative' => ['tests/fixtures/tls/distinctive-missing.pem'],
            'missing absolute' => ['/distinctive/missing/ca.pem'],
            'directory' => ['tests/fixtures/tls'],
        ];
    }

    #[DataProvider('unreadableCaFiles')]
    public function testUnreadableCaFileFails(string $path): void
    {
        putenv("SMTP_CA_FILE={$path}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['SMTP_CA_FILE: not readable'], $failures);
        $this->assertNoValueLeaks($failures, $path);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validEhloNames(): array
    {
        $label63 = str_repeat('a', 63);

        return [
            'single label' => ['localhost'],
            'dotted' => ['mail.example.com'],
            'inner hyphen' => ['mail-relay.example.com'],
            'digits in labels' => ['mx1.2example.com'],
            '63-byte label' => ["{$label63}.example"],
            '253 bytes' => [implode('.', [$label63, $label63, $label63, str_repeat('b', 61)])],
        ];
    }

    #[DataProvider('validEhloNames')]
    public function testValidEhloNamePasses(string $name): void
    {
        putenv("SMTP_EHLO_NAME={$name}");

        $this->assertSame([], (new ConfigValidator())->validate());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidEhloNames(): array
    {
        $label63 = str_repeat('a', 63);

        return [
            '64-byte label' => [str_repeat('a', 64) . '.example'],
            '254 bytes' => [implode('.', [$label63, $label63, $label63, str_repeat('b', 62)])],
            'leading hyphen' => ['-mail.example.com'],
            'trailing hyphen' => ['mail-.example.com'],
            'empty label' => ['mail..example.com'],
            'trailing dot' => ['mail.example.com.'],
            'underscore' => ['mail_relay.example.com'],
            'non-ascii' => ['mäil.example.com'],
            'space' => ['mail example.com'],
            'ipv4 literal' => ['192.0.2.10'],
            'ipv6 literal' => ['2001:db8::1'],
            'bracketed literal' => ['[192.0.2.10]'],
        ];
    }

    #[DataProvider('invalidEhloNames')]
    public function testInvalidEhloNameFails(string $name): void
    {
        putenv("SMTP_EHLO_NAME={$name}");

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['SMTP_EHLO_NAME: invalid hostname'], $failures);
        $this->assertNoValueLeaks($failures, $name);
    }

    public function testSmtpTransportSettingsAreNotPartOfTheSmtpEncryptionValidation(): void
    {
        putenv('SMTP_TOTAL_TIMEOUT=0');
        putenv('SMTP_CA_FILE=/distinctive/missing/ca.pem');
        putenv('SMTP_EHLO_NAME=-bad-');

        $this->assertSame([], (new ConfigValidator())->validateSmtpEncryption());
    }

    /**
     * @param list<string> $failures
     */
    private function assertNoOriginLeaks(array $failures, string $value): void
    {
        foreach (explode(',', $value) as $entry) {
            $this->assertNoValueLeaks($failures, $entry);
            $this->assertNoValueLeaks($failures, (string) preg_replace('#^[a-z]+://#', '', trim($entry)));
        }
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
