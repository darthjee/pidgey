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
final class ConfigValidatorSmtpKeyTest extends ConfigValidatorTestCase
{
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
}
