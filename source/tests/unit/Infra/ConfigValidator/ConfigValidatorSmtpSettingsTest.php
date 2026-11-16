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
final class ConfigValidatorSmtpSettingsTest extends ConfigValidatorTestCase
{
    public function testSmtpDefaultsPass(): void
    {
        $this->assertSame(10, ConfigValidator::DEFAULT_SMTP_CONNECT_TIMEOUT);
        $this->assertSame(10, ConfigValidator::DEFAULT_SMTP_COMMAND_TIMEOUT);
        $this->assertSame(25, ConfigValidator::DEFAULT_SMTP_TOTAL_TIMEOUT);
        $this->assertSame([], (new ConfigValidator())->validate());
    }

    public function testSmtpTestSettingsDefaultsAndValidValuesPass(): void
    {
        $this->assertSame(10, ConfigValidator::DEFAULT_SMTP_TEST_MAX_ATTEMPTS);
        $this->assertSame(3600, ConfigValidator::DEFAULT_SMTP_TEST_WINDOW);
        $this->assertSame([], (new ConfigValidator())->validate());

        putenv('SMTP_TEST_MAX_ATTEMPTS=3');
        putenv('SMTP_TEST_WINDOW=60');

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
            'absolute' => [dirname(__DIR__, 4) . '/tests/fixtures/tls/ca.pem'],
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
}
