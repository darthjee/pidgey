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
final class ConfigValidatorLogAndOriginsTest extends ConfigValidatorTestCase
{
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
}
