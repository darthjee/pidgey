<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Container;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\Transaction;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\UserRepository;
use RuntimeException;

#[CoversClass(Container::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(OrganizationRepository::class)]
#[UsesClass(UserRepository::class)]
final class ContainerTest extends TestCase
{
    private const VARIABLES = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
        }

        putenv('DB_HOST=db.example');
        putenv('DB_PORT=3307');
        putenv('DB_NAME=pidgey_example_test');
        putenv('DB_USER=someone');
        putenv('DB_PASSWORD=secret');
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    public function testConstructorConnectsNothing(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->never())->method('create');
        $factory->expects($this->never())->method('createServer');

        new Container($factory);
    }

    public function testConstructorNeedsNoDatabaseVariables(): void
    {
        putenv('DB_HOST');

        $this->assertInstanceOf(Container::class, new Container());
    }

    public function testPdoConnectsOnceWithTheEnvironmentConfig(): void
    {
        $pdo = $this->createStub(PDO::class);
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(new DatabaseConfig('db.example', 3307, 'pidgey_example_test', 'someone', 'secret'))
            ->willReturn($pdo);
        $container = new Container($factory);

        $this->assertSame($pdo, $container->pdo());
        $this->assertSame($pdo, $container->pdo());
    }

    public function testPdoReportsAMissingVariableByName(): void
    {
        putenv('DB_HOST');
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_HOST');

        (new Container($factory))->pdo();
    }

    public function testTransactionIsMemoizedAndWrapsTheSamePdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('commit')->willReturn(true);
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($pdo);
        $container = new Container($factory);

        $transaction = $container->transaction();

        $this->assertSame($transaction, $container->transaction());
        $this->assertSame($pdo, $container->pdo());
        $this->assertSame('ok', $transaction->run(static fn (): string => 'ok'));
    }

    public function testOrganizationRepositoryIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->organizationRepository();

        $this->assertInstanceOf(OrganizationRepository::class, $repository);
        $this->assertSame($repository, $container->organizationRepository());
        $container->userRepository();
        $container->transaction();
    }

    public function testUserRepositoryIsMemoizedOnASinglePdo(): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($this->createStub(PDO::class));
        $container = new Container($factory);

        $repository = $container->userRepository();

        $this->assertInstanceOf(UserRepository::class, $repository);
        $this->assertSame($repository, $container->userRepository());
        $container->organizationRepository();
        $container->pdo();
    }
}
