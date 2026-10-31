<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\EnvFile;
use Pidgey\Infra\EnvFileUnreadable;

#[CoversClass(EnvFile::class)]
#[CoversClass(EnvFileUnreadable::class)]
final class EnvFileTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/env';

    private const LOAD_KEYS = ['PIDGEY_TEST_LOAD_UNSET', 'PIDGEY_TEST_LOAD_SET', 'PIDGEY_TEST_LOAD_EMPTY'];

    private ?string $temporaryFile = null;

    protected function setUp(): void
    {
        $this->clearKeys();
    }

    protected function tearDown(): void
    {
        $this->clearKeys();

        if ($this->temporaryFile !== null && file_exists($this->temporaryFile)) {
            chmod($this->temporaryFile, 0600);
            unlink($this->temporaryFile);
        }
    }

    public function testParseReadsTheFixture(): void
    {
        $this->assertSame(
            [
                'PIDGEY_TEST_PLAIN' => 'plain value',
                'PIDGEY_TEST_DOUBLE' => 'a b',
                'PIDGEY_TEST_SINGLE' => 'c d',
                'PIDGEY_TEST_EQUALS' => 'a=b=c',
                'PIDGEY_TEST_EXPANSION' => '$HOME ${HOME}',
                'PIDGEY_TEST_EMPTY' => '',
                'PIDGEY_TEST_SPACED' => 'spaced',
                'PIDGEY_TEST_MISMATCHED' => '"open\'',
                'PIDGEY_TEST_INNER' => 'say "hi"',
                'PIDGEY_TEST_LONE' => '"',
            ],
            EnvFile::parse((string) file_get_contents(self::FIXTURES . '/valid.env')),
        );
    }

    public function testParseIgnoresCommentsAndBlankLines(): void
    {
        $this->assertSame([], EnvFile::parse("# KEY=value\n\n   \n  #OTHER=1\n"));
    }

    public function testParseSkipsExportLines(): void
    {
        $this->assertSame([], EnvFile::parse('export KEY=value'));
    }

    public function testParseSkipsLinesWithoutEquals(): void
    {
        $this->assertSame([], EnvFile::parse('KEY'));
    }

    public function testParseSkipsInvalidKeys(): void
    {
        $this->assertSame([], EnvFile::parse("1KEY=a\nKEY-NAME=b\n=c\nKE Y=d"));
    }

    public function testParseHandlesCrLfLineEndings(): void
    {
        $this->assertSame(['A' => '1', 'B' => '2'], EnvFile::parse("A=1\r\nB=2\r\n"));
    }

    public function testParseStripsOnlyOnePairOfQuotes(): void
    {
        $this->assertSame(['A' => '"x"', 'B' => "'y'"], EnvFile::parse("A=\"\"x\"\"\nB=''y''"));
    }

    public function testParseKeepsTheLastDuplicate(): void
    {
        $this->assertSame(['A' => '2'], EnvFile::parse("A=1\nA=2"));
    }

    public function testLoadFillsUnsetVariables(): void
    {
        EnvFile::load(self::FIXTURES . '/load.env');

        $this->assertSame('from file', getenv('PIDGEY_TEST_LOAD_UNSET'));
    }

    public function testLoadNeverOverridesASetVariable(): void
    {
        putenv('PIDGEY_TEST_LOAD_SET=from environment');

        EnvFile::load(self::FIXTURES . '/load.env');

        $this->assertSame('from environment', getenv('PIDGEY_TEST_LOAD_SET'));
    }

    public function testLoadNeverOverridesAVariableSetToEmpty(): void
    {
        putenv('PIDGEY_TEST_LOAD_EMPTY=');

        EnvFile::load(self::FIXTURES . '/load.env');

        $this->assertSame('', getenv('PIDGEY_TEST_LOAD_EMPTY'));
    }

    public function testLoadSetsEmptyValues(): void
    {
        $this->temporaryFile = $this->writeTemporaryFile("PIDGEY_TEST_LOAD_UNSET=\n");

        EnvFile::load($this->temporaryFile);

        $this->assertSame('', getenv('PIDGEY_TEST_LOAD_UNSET'));
    }

    public function testLoadIgnoresAMissingFile(): void
    {
        EnvFile::load(self::FIXTURES . '/does-not-exist.env');

        foreach (self::LOAD_KEYS as $key) {
            $this->assertFalse(getenv($key));
        }
    }

    public function testLoadThrowsForAnUnreadableFile(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can read files regardless of permissions');
        }

        $this->temporaryFile = $this->writeTemporaryFile("PIDGEY_TEST_LOAD_UNSET=secret-content\n");
        chmod($this->temporaryFile, 0000);

        try {
            EnvFile::load($this->temporaryFile);
            $this->fail('Expected EnvFileUnreadable');
        } catch (EnvFileUnreadable $exception) {
            $this->assertStringNotContainsString('secret-content', $exception->getMessage());
        }

        $this->assertFalse(getenv('PIDGEY_TEST_LOAD_UNSET'));
    }

    public function testLoadThrowsForADirectory(): void
    {
        $this->expectException(EnvFileUnreadable::class);

        EnvFile::load(self::FIXTURES);
    }

    private function writeTemporaryFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'pidgey-env-');
        file_put_contents($path, $contents);

        return $path;
    }

    private function clearKeys(): void
    {
        foreach (self::LOAD_KEYS as $key) {
            putenv($key);
        }
    }
}
