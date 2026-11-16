<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra\ConfigValidator;

use PHPUnit\Framework\Attributes\CoversClass;
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
final class ConfigValidatorSmtpEncryptionTest extends ConfigValidatorTestCase
{
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
}
