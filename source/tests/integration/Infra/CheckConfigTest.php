<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Infra;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real `bin/check-config` script as a process, inside a temporary app root.
 */
#[CoversNothing]
final class CheckConfigTest extends TestCase
{
    private const SOURCE_ROOT = __DIR__ . '/../../..';

    private const SECRET_KEY = 'distinctive-secret-key-6f1c2a9e8b7d4c3a';
    private const PREVIOUS_KEY = 'distinctive-previous-key-0d9e8f7a6b5c4d3e';
    private const DB_HOST = 'distinctive-db-host.example';
    private const DB_NAME = 'distinctive_db_name';
    private const DB_USER = 'distinctive_db_user';
    private const DB_PASSWORD = 'distinctive-db-password-91b2c3';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/pidgey-check-config-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/bin', 0700, true);
        mkdir($this->root . '/config', 0700);
        copy(self::SOURCE_ROOT . '/bin/check-config', $this->root . '/bin/check-config');
        symlink((string) realpath(self::SOURCE_ROOT . '/vendor'), $this->root . '/vendor');
    }

    protected function tearDown(): void
    {
        $envFile = $this->root . '/config/.env';

        if (file_exists($envFile)) {
            chmod($envFile, 0600);
            unlink($envFile);
        }

        rmdir($this->root . '/config');
        unlink($this->root . '/bin/check-config');
        rmdir($this->root . '/bin');
        unlink($this->root . '/vendor');
        rmdir($this->root);
    }

    public function testValidConfigurationFromTheEnvFilePasses(): void
    {
        $this->writeEnvFile(self::validFile());

        $result = $this->runCheckConfig([]);

        $this->assertSame(0, $result['exit']);
        $this->assertSame("configuration OK\n", $result['stdout']);
        $this->assertSame('', $result['stderr']);
        $this->assertNoValueLeaks($result);
    }

    public function testValidConfigurationFromTheProcessEnvironmentPasses(): void
    {
        $result = $this->runCheckConfig(self::validVariables());

        $this->assertSame(0, $result['exit']);
        $this->assertSame("configuration OK\n", $result['stdout']);
        $this->assertNoValueLeaks($result);
    }

    public function testProcessEnvironmentWinsOverTheEnvFile(): void
    {
        $this->writeEnvFile(self::validFile() . "SECRET_KEY=short-file-key\n");

        $result = $this->runCheckConfig(['SECRET_KEY' => self::SECRET_KEY]);

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertNoValueLeaks($result);
    }

    public function testEnvFileFillsWhatTheProcessEnvironmentLacks(): void
    {
        $this->writeEnvFile('SECRET_KEY=' . self::SECRET_KEY . "\n");

        $variables = self::validVariables();
        unset($variables['SECRET_KEY']);

        $result = $this->runCheckConfig($variables);

        $this->assertSame(0, $result['exit'], $result['stderr']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidConfigurations(): array
    {
        return [
            'missing secret key' => ["SECRET_KEY=\n", 'SECRET_KEY: missing'],
            'short secret key' => ["SECRET_KEY=short-secret-value\n", 'SECRET_KEY: too short'],
            'bad previous key' => [
                'PREVIOUS_SECRET_KEYS=' . self::PREVIOUS_KEY . ",short-previous-value\n",
                'PREVIOUS_SECRET_KEYS: entry too short',
            ],
            'missing db host' => ["DB_HOST=\n", 'DB_HOST: missing'],
        ];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationFails(string $override, string $expected): void
    {
        // Replace the setting's valid line with the override.
        $name = substr($override, 0, (int) strpos($override, '='));
        $lines = array_filter(
            explode("\n", self::validFile()),
            static fn (string $line): bool => !str_starts_with($line, "{$name}="),
        );
        $this->writeEnvFile(implode("\n", $lines) . "\n" . $override);

        $result = $this->runCheckConfig([]);

        $this->assertSame(1, $result['exit']);
        $this->assertSame('', $result['stdout']);
        $this->assertSame("{$expected}\n", $result['stderr']);
        $this->assertNoValueLeaks($result);
        $this->assertStringNotContainsString('short-secret-value', $result['stderr']);
        $this->assertStringNotContainsString('short-previous-value', $result['stderr']);
    }

    public function testUnreadableEnvFileFails(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can read files regardless of permissions');
        }

        $this->writeEnvFile(self::validFile());
        chmod($this->root . '/config/.env', 0000);

        $result = $this->runCheckConfig(self::validVariables());

        $this->assertSame(1, $result['exit']);
        $this->assertSame('', $result['stdout']);
        $this->assertSame("env file: unreadable\n", $result['stderr']);
        $this->assertNoValueLeaks($result);
    }

    /**
     * @return array<string, string>
     */
    private static function validVariables(): array
    {
        return [
            'SECRET_KEY' => self::SECRET_KEY,
            'PREVIOUS_SECRET_KEYS' => self::PREVIOUS_KEY,
            'DB_HOST' => self::DB_HOST,
            'DB_NAME' => self::DB_NAME,
            'DB_USER' => self::DB_USER,
            'DB_PASSWORD' => self::DB_PASSWORD,
        ];
    }

    private static function validFile(): string
    {
        $contents = '';

        foreach (self::validVariables() as $name => $value) {
            $contents .= "{$name}={$value}\n";
        }

        return $contents;
    }

    private function writeEnvFile(string $contents): void
    {
        file_put_contents($this->root . '/config/.env', $contents);
    }

    /**
     * @param array<string, string> $variables Complete process environment.
     *
     * @return array{exit: integer, stdout: string, stderr: string}
     */
    private function runCheckConfig(array $variables): array
    {
        $process = proc_open(
            [PHP_BINARY, $this->root . '/bin/check-config'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root,
            $variables,
        );
        $this->assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /**
     * @param array{exit: integer, stdout: string, stderr: string} $result
     */
    private function assertNoValueLeaks(array $result): void
    {
        foreach (self::validVariables() as $value) {
            $this->assertStringNotContainsString($value, $result['stdout']);
            $this->assertStringNotContainsString($value, $result['stderr']);
        }
    }
}
