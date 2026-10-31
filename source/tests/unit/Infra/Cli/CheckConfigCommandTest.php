<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Cli\CheckConfigCommand;
use Pidgey\Infra\ConfigValidator;
use Pidgey\Infra\Env;
use Pidgey\Infra\EnvFile;
use Pidgey\Infra\EnvFileUnreadable;

#[CoversClass(CheckConfigCommand::class)]
#[UsesClass(ConfigValidator::class)]
#[UsesClass(Env::class)]
#[UsesClass(EnvFile::class)]
#[UsesClass(EnvFileUnreadable::class)]
final class CheckConfigCommandTest extends TestCase
{
    private const MISSING_FILE = __DIR__ . '/../../../fixtures/env/does-not-exist.env';
    private const LOADED_KEY = 'PIDGEY_TEST_CHECK_CONFIG_LOADED';

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    private ?string $temporaryFile = null;

    protected function setUp(): void
    {
        $this->stdout = fopen('php://memory', 'w+');
        $this->stderr = fopen('php://memory', 'w+');
        putenv(self::LOADED_KEY);
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);
        putenv(self::LOADED_KEY);

        if ($this->temporaryFile !== null && file_exists($this->temporaryFile)) {
            chmod($this->temporaryFile, 0600);
            unlink($this->temporaryFile);
        }
    }

    public function testSuccessPrintsConfigurationOk(): void
    {
        $this->assertSame(0, $this->command([])->run());
        $this->assertSame("configuration OK\n", $this->read($this->stdout));
        $this->assertSame('', $this->read($this->stderr));
    }

    public function testFailuresArePrintedOnePerLine(): void
    {
        $command = $this->command(['SECRET_KEY: missing', 'DB_HOST: missing']);

        $this->assertSame(1, $command->run());
        $this->assertSame('', $this->read($this->stdout));
        $this->assertSame("SECRET_KEY: missing\nDB_HOST: missing\n", $this->read($this->stderr));
    }

    /**
     * @return array<string, array{string, integer}>
     */
    public static function phpVersions(): array
    {
        return [
            '8.4.0' => ['8.4.0', 0],
            '8.4.27' => ['8.4.27', 0],
            '8.3.0' => ['8.3.0', 1],
            '8.5.0' => ['8.5.0', 1],
            '8.40.0' => ['8.40.0', 1],
        ];
    }

    #[DataProvider('phpVersions')]
    public function testPhpVersionMustBe84(string $version, int $exitCode): void
    {
        $this->assertSame($exitCode, $this->command([], $version)->run());
        $this->assertSame($exitCode === 0 ? '' : "PHP version: not 8.4.x\n", $this->read($this->stderr));
    }

    public function testPhpVersionDefaultsToTheRunningPhp(): void
    {
        $command = new CheckConfigCommand(self::MISSING_FILE, static fn (): array => [], $this->stdout, $this->stderr);

        $this->assertSame(str_starts_with(PHP_VERSION, '8.4.') ? 0 : 1, $command->run());
    }

    public function testLoadsTheEnvFileBeforeValidating(): void
    {
        $this->temporaryFile = $this->writeTemporaryFile(self::LOADED_KEY . "=yes\n");
        $seen = null;

        $command = new CheckConfigCommand(
            $this->temporaryFile,
            static function () use (&$seen): array {
                $seen = getenv(self::LOADED_KEY);

                return [];
            },
            $this->stdout,
            $this->stderr,
            '8.4.0',
        );

        $this->assertSame(0, $command->run());
        $this->assertSame('yes', $seen);
    }

    public function testUnreadableEnvFileIsReportedAlongsideOtherFailures(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can read files regardless of permissions');
        }

        $this->temporaryFile = $this->writeTemporaryFile("SECRET_KEY=unreadable-secret-content\n");
        chmod($this->temporaryFile, 0000);

        $command = new CheckConfigCommand(
            $this->temporaryFile,
            static fn (): array => ['SECRET_KEY: missing'],
            $this->stdout,
            $this->stderr,
            '8.3.0',
        );

        $this->assertSame(1, $command->run());
        $this->assertSame(
            "env file: unreadable\nSECRET_KEY: missing\nPHP version: not 8.4.x\n",
            $this->read($this->stderr),
        );
    }

    public function testCreateUsesTheConfigurationValidator(): void
    {
        $saved = getenv('SECRET_KEY');
        putenv('SECRET_KEY');

        try {
            $exitCode = CheckConfigCommand::create(self::MISSING_FILE, $this->stdout, $this->stderr)->run();
        } finally {
            putenv($saved === false ? 'SECRET_KEY' : "SECRET_KEY={$saved}");
        }

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("SECRET_KEY: missing\n", $this->read($this->stderr));
    }

    /**
     * @param list<string> $failures
     */
    private function command(array $failures, string $phpVersion = '8.4.0'): CheckConfigCommand
    {
        return new CheckConfigCommand(
            self::MISSING_FILE,
            static fn (): array => $failures,
            $this->stdout,
            $this->stderr,
            $phpVersion,
        );
    }

    private function writeTemporaryFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'pidgey-check-config-');
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @param resource $stream
     */
    private function read($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
