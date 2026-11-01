<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra\Cli;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Cli\DatabaseCommand;
use Pidgey\Infra\Clock;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\DatabaseServer;
use Pidgey\Infra\Env;
use Pidgey\Infra\Migrations\Migration;
use Pidgey\Infra\Migrations\MigrationException;
use Pidgey\Infra\Migrations\MigrationResult;
use Pidgey\Infra\Migrations\MigrationState;
use Pidgey\Infra\Migrations\MigrationStatus;
use Pidgey\Infra\Migrations\Migrator;
use Pidgey\Infra\PdoFactory;
use RuntimeException;

#[CoversClass(DatabaseCommand::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(DatabaseServer::class)]
#[UsesClass(Env::class)]
#[UsesClass(Migration::class)]
#[UsesClass(MigrationResult::class)]
#[UsesClass(MigrationStatus::class)]
#[UsesClass(PdoFactory::class)]
final class DatabaseCommandTest extends TestCase
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    private DatabaseConfig $config;

    /** @var list<DatabaseConfig> Configs passed to the server and migrator factories. */
    private array $received = [];

    protected function setUp(): void
    {
        $this->stdout = fopen('php://memory', 'w+');
        $this->stderr = fopen('php://memory', 'w+');
        $this->config = new DatabaseConfig('db.example', 3307, 'pidgey_test', 'user', 'secret');
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);
    }

    public function testWaitForDbSucceeds(): void
    {
        $command = $this->command(server: $this->server($this->createStub(PDO::class)));

        $this->assertSame(0, $command->waitForDb());
        $this->assertSame("MySQL at db.example:3307 is ready.\n", $this->read($this->stdout));
        $this->assertSame('', $this->read($this->stderr));
    }

    public function testWaitForDbFailsOnTimeout(): void
    {
        $command = $this->command(server: $this->server(null), waitTimeout: 0);

        $this->assertSame(1, $command->waitForDb());
        $this->assertSame('', $this->read($this->stdout));
        $this->assertSame(
            "MySQL at db.example:3307 did not accept connections within 0s: Connection refused\n",
            $this->read($this->stderr),
        );
    }

    public function testCreateDbCreatesConfiguredDatabase(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('exec')
            ->with($this->stringStartsWith('CREATE DATABASE IF NOT EXISTS `pidgey_test`'))
            ->willReturn(1);

        $this->assertSame(0, $this->command(server: $this->server($pdo))->createDb());
        $this->assertSame("Database pidgey_test is ready.\n", $this->read($this->stdout));
    }

    public function testDropDbDropsConfiguredDatabase(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('exec')
            ->with('DROP DATABASE IF EXISTS `pidgey_test`')
            ->willReturn(1);

        $this->assertSame(0, $this->command(server: $this->server($pdo))->dropDb());
        $this->assertSame("Database pidgey_test dropped.\n", $this->read($this->stdout));
    }

    public function testCreateDbReportsServerErrors(): void
    {
        $this->assertSame(1, $this->command(server: $this->server(null))->createDb());
        $this->assertSame("Connection refused\n", $this->read($this->stderr));
    }

    public function testConfigurationErrorsAreReported(): void
    {
        $command = $this->command(migrationConfigLoader: static function (): DatabaseConfig {
            throw new RuntimeException('Missing required environment variable: DB_HOST');
        });

        $this->assertSame(1, $command->dropDb());
        $this->assertSame("Missing required environment variable: DB_HOST\n", $this->read($this->stderr));
    }

    public function testWaitForDbUsesTheAppUser(): void
    {
        $command = $this->routingCommand(server: $this->server($this->createStub(PDO::class)));

        $this->assertSame(0, $command->waitForDb());
        $this->assertReceivedUser('app');
    }

    public function testCreateDbUsesTheMigrationUser(): void
    {
        $command = $this->routingCommand(server: $this->server($this->createStub(PDO::class)));

        $this->assertSame(0, $command->createDb());
        $this->assertReceivedUser('migrator');
    }

    public function testDropDbUsesTheMigrationUser(): void
    {
        $command = $this->routingCommand(server: $this->server($this->createStub(PDO::class)));

        $this->assertSame(0, $command->dropDb());
        $this->assertReceivedUser('migrator');
    }

    public function testMigrateUsesTheMigrationUser(): void
    {
        $migrator = $this->createStub(Migrator::class);
        $migrator->method('migrate')->willReturn(new MigrationResult([]));

        $this->assertSame(0, $this->routingCommand(migrator: $migrator)->migrate([]));
        $this->assertReceivedUser('migrator');
    }

    public function testStatusUsesTheMigrationUser(): void
    {
        $migrator = $this->createStub(Migrator::class);
        $migrator->method('status')->willReturn([]);

        $this->assertSame(0, $this->routingCommand(migrator: $migrator)->migrate(['--status']));
        $this->assertReceivedUser('migrator');
    }

    public function testMigratePrintsAppliedFiles(): void
    {
        $migrator = $this->createStub(Migrator::class);
        $migrator->method('migrate')->willReturn(new MigrationResult([
            new Migration(1, 'create_widgets', '/m/0001_create_widgets.sql', 'SELECT 1', 'x'),
            new Migration(2, 'create_gadgets', '/m/0002_create_gadgets.sql', 'SELECT 2', 'y'),
        ]));

        $this->assertSame(0, $this->command(migrator: $migrator)->migrate([]));
        $this->assertSame(
            "Applied 0001_create_widgets.sql\nApplied 0002_create_gadgets.sql\n",
            $this->read($this->stdout),
        );
    }

    public function testMigrateWithNothingPending(): void
    {
        $migrator = $this->createStub(Migrator::class);
        $migrator->method('migrate')->willReturn(new MigrationResult([]));

        $this->assertSame(0, $this->command(migrator: $migrator)->migrate([]));
        $this->assertSame("Nothing to migrate.\n", $this->read($this->stdout));
    }

    public function testMigrateReportsFailures(): void
    {
        $migrator = $this->createStub(Migrator::class);
        $migrator->method('migrate')->willThrowException(
            new MigrationException('Migration failed: /m/0001_x.sql: boom'),
        );

        $this->assertSame(1, $this->command(migrator: $migrator)->migrate([]));
        $this->assertSame('', $this->read($this->stdout));
        $this->assertSame("Migration failed: /m/0001_x.sql: boom\n", $this->read($this->stderr));
    }

    public function testStatusPrintsTable(): void
    {
        $migrator = $this->createMock(Migrator::class);
        $migrator->expects($this->never())->method('migrate');
        $migrator->method('status')->willReturn([
            new MigrationStatus(1, 'create_widgets', MigrationState::Applied, '/m/0001_create_widgets.sql'),
            new MigrationStatus(2, 'gone', MigrationState::MissingFile, null),
            new MigrationStatus(3, 'edited', MigrationState::ChecksumMismatch, '/m/0003_edited.sql'),
            new MigrationStatus(10, 'next', MigrationState::Pending, '/m/0010_next.sql'),
        ]);

        $this->assertSame(0, $this->command(migrator: $migrator)->migrate(['--status']));
        $this->assertSame(
            "Version  Name            State\n"
            . "0001     create_widgets  applied\n"
            . "0002     gone            missing_file\n"
            . "0003     edited          checksum_mismatch\n"
            . "0010     next            pending\n",
            $this->read($this->stdout),
        );
    }

    public function testStatusWithoutMigrations(): void
    {
        $migrator = $this->createStub(Migrator::class);
        $migrator->method('status')->willReturn([]);

        $this->assertSame(0, $this->command(migrator: $migrator)->migrate(['--status']));
        $this->assertSame("No migrations.\n", $this->read($this->stdout));
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('invalidArguments')]
    public function testMigrateRejectsUnknownArguments(array $arguments): void
    {
        $migrator = $this->createMock(Migrator::class);
        $migrator->expects($this->never())->method('migrate');
        $migrator->expects($this->never())->method('status');

        $this->assertSame(2, $this->command(migrator: $migrator)->migrate($arguments));
        $this->assertSame('', $this->read($this->stdout));
        $this->assertSame("Usage: migrate [--status]\n", $this->read($this->stderr));
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function invalidArguments(): array
    {
        return [
            'unknown flag' => [['--force']],
            'extra argument' => [['--status', 'now']],
            'positional' => [['up']],
        ];
    }

    public function testFromEnvironmentReportsMissingConfiguration(): void
    {
        $saved = getenv('DB_HOST');
        putenv('DB_HOST');

        try {
            $command = DatabaseCommand::fromEnvironment('/nowhere', $this->stdout, $this->stderr);

            $this->assertSame(1, $command->createDb());
            $this->assertSame("Missing required environment variable: DB_HOST\n", $this->read($this->stderr));
        } finally {
            if ($saved !== false) {
                putenv("DB_HOST={$saved}");
            }
        }
    }

    private function command(
        ?\Closure $configLoader = null,
        ?DatabaseServer $server = null,
        ?Migrator $migrator = null,
        int $waitTimeout = 5,
        ?\Closure $migrationConfigLoader = null,
    ): DatabaseCommand {
        $config = $this->config;

        return new DatabaseCommand(
            $configLoader ?? static fn (): DatabaseConfig => $config,
            $migrationConfigLoader ?? static fn (): DatabaseConfig => $config,
            function (DatabaseConfig $received) use ($server): DatabaseServer {
                $this->received[] = $received;

                return $server ?? throw new RuntimeException('no server');
            },
            function (DatabaseConfig $received) use ($migrator): Migrator {
                $this->received[] = $received;

                return $migrator ?? throw new RuntimeException('no migrator');
            },
            static fn (): int => $waitTimeout,
            $this->stdout,
            $this->stderr,
        );
    }

    /**
     * Builds a command whose app and migration loaders return configs with different users.
     */
    private function routingCommand(?DatabaseServer $server = null, ?Migrator $migrator = null): DatabaseCommand
    {
        $app = new DatabaseConfig('db.example', 3307, 'pidgey_test', 'app', 'app-secret');
        $migration = new DatabaseConfig('db.example', 3307, 'pidgey_test', 'migrator', 'schema-secret');

        return $this->command(
            configLoader: static fn (): DatabaseConfig => $app,
            server: $server,
            migrator: $migrator,
            migrationConfigLoader: static fn (): DatabaseConfig => $migration,
        );
    }

    private function assertReceivedUser(string $user): void
    {
        $this->assertCount(1, $this->received);
        $this->assertSame($user, $this->received[0]->user);
    }

    private function server(?PDO $pdo): DatabaseServer
    {
        $factory = $this->createStub(ConnectionFactory::class);
        $factory->method('createServer')->willReturnCallback(
            static fn (): PDO => $pdo ?? throw new PDOException('Connection refused'),
        );

        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(0.0);

        return new DatabaseServer($factory, $this->config, $clock);
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
