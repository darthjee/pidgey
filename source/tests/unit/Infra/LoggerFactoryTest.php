<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use DateTimeImmutable;
use InvalidArgumentException;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;

#[CoversClass(LoggerFactory::class)]
#[UsesClass(Env::class)]
final class LoggerFactoryTest extends TestCase
{
    private string|false $savedLevel = false;

    protected function setUp(): void
    {
        $this->savedLevel = getenv('LOG_LEVEL');
        putenv('LOG_LEVEL');
    }

    protected function tearDown(): void
    {
        putenv($this->savedLevel === false ? 'LOG_LEVEL' : "LOG_LEVEL={$this->savedLevel}");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validLevels(): array
    {
        $cases = [];

        foreach (LoggerFactory::LEVELS as $level) {
            $cases[$level] = [$level];
        }

        return $cases;
    }

    #[DataProvider('validLevels')]
    public function testEveryLevelIsAccepted(string $level): void
    {
        $handler = new TestHandler();

        LoggerFactory::create($level, $handler);

        $this->assertSame(Level::fromName($level), $handler->getLevel());
    }

    public function testRecordsBelowTheLevelAreNotHandled(): void
    {
        $handler = new TestHandler();
        $logger = LoggerFactory::create('info', $handler);

        $logger->debug('below the level');
        $logger->info('at the level');

        $this->assertFalse($handler->hasDebugRecords());
        $this->assertTrue($handler->hasInfoThatContains('at the level'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLevels(): array
    {
        return [
            'upper case' => ['INFO'],
            'leading space' => [' info'],
            'unknown name' => ['warn'],
        ];
    }

    #[DataProvider('invalidLevels')]
    public function testInvalidLevelThrowsWithoutTheValue(string $level): void
    {
        try {
            LoggerFactory::create($level, new TestHandler());
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('LOG_LEVEL: invalid level', $exception->getMessage());
        }
    }

    public function testChannelIsPidgey(): void
    {
        $handler = new TestHandler();
        $logger = LoggerFactory::create('debug', $handler);

        $logger->info('hello');

        $this->assertSame('pidgey', $handler->getRecords()[0]->channel);
        $this->assertStringStartsWith('pidgey.INFO: hello', $handler->getRecords()[0]->formatted);
    }

    public function testMessageAndContextLineBreaksAreFlattened(): void
    {
        $handler = new TestHandler();
        $logger = LoggerFactory::create('debug', $handler);

        $logger->warning("first\nsecond", ['detail' => "one\ntwo"]);

        $formatted = $handler->getRecords()[0]->formatted;

        $this->assertStringNotContainsString("\n", $formatted);
        $this->assertStringContainsString('first second', $formatted);
    }

    public function testDefaultHandlerIsAnErrorLogHandler(): void
    {
        $logger = LoggerFactory::create('info');

        $this->assertInstanceOf(Logger::class, $logger);
        $handlers = $logger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(ErrorLogHandler::class, $handlers[0]);
    }

    public function testDefaultHandlerFormatsOneLine(): void
    {
        $logger = LoggerFactory::create('info');
        $this->assertInstanceOf(Logger::class, $logger);
        $handler = $logger->getHandlers()[0];
        $this->assertInstanceOf(ErrorLogHandler::class, $handler);

        $record = new LogRecord(
            new DateTimeImmutable(),
            'pidgey',
            Level::Error,
            "broken\nmessage",
            ['detail' => "a\nb"],
        );

        $formatted = $handler->getFormatter()->format($record);

        $this->assertStringStartsWith('pidgey.ERROR: broken message', $formatted);
        $this->assertStringNotContainsString("\n", $formatted);
        $this->assertSame(Level::Info, $handler->getLevel());
    }

    /**
     * @return array<string, array{string|null, Level}>
     */
    public static function environmentLevels(): array
    {
        return [
            'unset' => [null, Level::Info],
            'empty' => ['', Level::Info],
            'given' => ['error', Level::Error],
        ];
    }

    #[DataProvider('environmentLevels')]
    public function testFromEnvReadsLogLevel(?string $value, Level $expected): void
    {
        putenv($value === null ? 'LOG_LEVEL' : "LOG_LEVEL={$value}");

        $logger = LoggerFactory::fromEnv();

        $this->assertInstanceOf(Logger::class, $logger);
        $handler = $logger->getHandlers()[0];
        $this->assertInstanceOf(ErrorLogHandler::class, $handler);
        $this->assertSame($expected, $handler->getLevel());
    }
}
