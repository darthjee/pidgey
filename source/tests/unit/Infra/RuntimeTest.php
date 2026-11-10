<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\ConfigValidator;
use Pidgey\Infra\Env;
use Pidgey\Infra\Runtime;

#[CoversClass(Runtime::class)]
#[UsesClass(Env::class)]
#[UsesClass(ConfigValidator::class)]
final class RuntimeTest extends TestCase
{
    private const SETTING = 'zend.exception_ignore_args';

    private string|false $savedEnv = false;

    private string|false $savedIni = false;

    protected function setUp(): void
    {
        $this->savedEnv = getenv('PHP_ENV');
        $this->savedIni = ini_get(self::SETTING);
        ini_set(self::SETTING, '0');
    }

    protected function tearDown(): void
    {
        putenv($this->savedEnv === false ? 'PHP_ENV' : "PHP_ENV={$this->savedEnv}");
        ini_set(self::SETTING, $this->savedIni === false ? '0' : $this->savedIni);
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function environments(): array
    {
        return [
            'production' => ['production', '1'],
            'unset' => [null, '1'],
            'unknown' => ['staging', '1'],
            'development' => ['development', '0'],
            'test' => ['test', '0'],
        ];
    }

    #[DataProvider('environments')]
    public function testHardenDependsOnTheEnvironment(?string $environment, string $expected): void
    {
        putenv($environment === null ? 'PHP_ENV' : "PHP_ENV={$environment}");

        Runtime::harden();

        $this->assertSame($expected, ini_get(self::SETTING));
    }
}
