<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Clock;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\DatabaseServer;
use RuntimeException;

#[CoversClass(DatabaseServer::class)]
#[UsesClass(DatabaseConfig::class)]
final class DatabaseServerTest extends TestCase
{
    private DatabaseConfig $config;

    private int $attempts = 0;

    protected function setUp(): void
    {
        $this->config = new DatabaseConfig('db.example', 3307, 'pidgey_test', 'user', 'secret');
    }

    public function testWaitReturnsOnFirstSuccess(): void
    {
        $factory = $this->failingFactory(0);
        $clock = $this->fakeClock();

        (new DatabaseServer($factory, $this->config, $clock))->waitUntilReady(10);

        $this->assertSame(1, $this->attempts);
        $this->assertSame([], $clock->sleeps);
    }

    public function testWaitSucceedsAfterFailures(): void
    {
        $factory = $this->failingFactory(3);
        $clock = $this->fakeClock();

        (new DatabaseServer($factory, $this->config, $clock))->waitUntilReady(10);

        $this->assertSame(4, $this->attempts);
        $this->assertSame([1.0, 1.0, 1.0], $clock->sleeps);
    }

    public function testWaitTimesOutWithLastError(): void
    {
        $factory = $this->failingFactory(PHP_INT_MAX);
        $clock = $this->fakeClock();

        try {
            (new DatabaseServer($factory, $this->config, $clock))->waitUntilReady(3);
            $this->fail('Expected a RuntimeException');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'MySQL at db.example:3307 did not accept connections within 3s: Connection refused (4)',
                $exception->getMessage(),
            );
        }

        $this->assertSame(4, $this->attempts);
        $this->assertSame([1.0, 1.0, 1.0], $clock->sleeps);
    }

    public function testCreateDatabase(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('exec')
            ->with('CREATE DATABASE IF NOT EXISTS `pidgey_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci')
            ->willReturn(1);

        (new DatabaseServer($this->serverFactory($pdo), $this->config, $this->fakeClock()))
            ->createDatabase('pidgey_test');
    }

    public function testDropDatabase(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('exec')
            ->with('DROP DATABASE IF EXISTS `pidgey_test`')
            ->willReturn(0);

        (new DatabaseServer($this->serverFactory($pdo), $this->config, $this->fakeClock()))
            ->dropDatabase('pidgey_test');
    }

    #[DataProvider('invalidNames')]
    public function testCreateRejectsInvalidName(string $name): void
    {
        $factory = $this->failingFactory(0);
        $server = new DatabaseServer($factory, $this->config, $this->fakeClock());

        try {
            $server->createDatabase($name);
            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame("Invalid database name: {$name}", $exception->getMessage());
        }

        $this->assertSame(0, $this->attempts);
    }

    #[DataProvider('invalidNames')]
    public function testDropRejectsInvalidName(string $name): void
    {
        $factory = $this->failingFactory(0);

        $this->expectException(InvalidArgumentException::class);

        try {
            (new DatabaseServer($factory, $this->config, $this->fakeClock()))->dropDatabase($name);
        } finally {
            $this->assertSame(0, $this->attempts);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'backtick' => ['pidgey`; DROP DATABASE x; --'],
            'dash' => ['pidgey-test'],
            'dot' => ['pidgey.test'],
            'space' => ['pidgey test'],
            'too long' => [str_repeat('a', 65)],
        ];
    }

    private function failingFactory(int $failures): ConnectionFactory
    {
        $pdo = $this->createStub(PDO::class);
        $factory = $this->createStub(ConnectionFactory::class);

        $factory->method('createServer')->willReturnCallback(
            function () use ($failures, $pdo): PDO {
                $this->attempts++;

                if ($this->attempts <= $failures) {
                    throw new PDOException("Connection refused ({$this->attempts})");
                }

                return $pdo;
            },
        );

        return $factory;
    }

    private function serverFactory(PDO $pdo): ConnectionFactory
    {
        $factory = $this->createStub(ConnectionFactory::class);
        $factory->method('createServer')->willReturn($pdo);

        return $factory;
    }

    private function fakeClock(): Clock
    {
        return new class () implements Clock {
            /** @var list<float> */
            public array $sleeps = [];

            private float $time = 100.0;

            public function now(): float
            {
                return $this->time;
            }

            public function sleep(float $seconds): void
            {
                $this->sleeps[] = $seconds;
                $this->time += $seconds;
            }
        };
    }
}
