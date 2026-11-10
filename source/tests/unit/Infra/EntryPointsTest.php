<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Every entrypoint that loads the env file hardens the runtime right after.
 */
#[CoversNothing]
final class EntryPointsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function testEveryEntryPointLoadingTheEnvFileHardensTheRuntime(): void
    {
        $checked = 0;

        foreach ($this->entryPoints() as $path) {
            $code = (string) file_get_contents($path);

            if (!str_contains($code, 'EnvFile::load') && !str_contains($code, 'CheckConfigCommand')) {
                continue;
            }

            $checked++;
            $this->assertTrue(
                $this->hardens($code),
                sprintf('%s loads the env file without calling Runtime::harden()', basename($path)),
            );
        }

        $this->assertGreaterThan(0, $checked);
    }

    /**
     * @return list<string>
     */
    private function entryPoints(): array
    {
        $files = glob(self::ROOT . '/bin/*') ?: [];

        return [self::ROOT . '/public/index.php', ...array_values(array_filter($files, 'is_file'))];
    }

    /**
     * A script delegating to `CheckConfigCommand` is covered by the command itself.
     */
    private function hardens(string $code): bool
    {
        if (str_contains($code, 'Runtime::harden()')) {
            return true;
        }

        $command = (string) file_get_contents(self::ROOT . '/lib/Infra/Cli/CheckConfigCommand.php');

        return str_contains($code, 'CheckConfigCommand') && str_contains($command, 'Runtime::harden()');
    }
}
