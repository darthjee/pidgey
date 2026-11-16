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
final class ConfigValidatorSecretKeyTest extends ConfigValidatorTestCase
{
    public function testPreviousKeyFailuresAreReportedOnceEach(): void
    {
        putenv('PREVIOUS_SECRET_KEYS=short-one,,short-two,');

        $failures = (new ConfigValidator())->validate();

        $this->assertSame(['PREVIOUS_SECRET_KEYS: empty entry', 'PREVIOUS_SECRET_KEYS: entry too short'], $failures);
        $this->assertNoValueLeaks($failures, 'short-one');
        $this->assertNoValueLeaks($failures, 'short-two');
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
}
