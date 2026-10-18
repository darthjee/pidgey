<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Infra\Migrations;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\DatabaseServer;
use Pidgey\Infra\Env;
use Pidgey\Infra\Migrations\Migration;
use Pidgey\Infra\Migrations\MigrationException;
use Pidgey\Infra\Migrations\MigrationLoader;
use Pidgey\Infra\Migrations\MigrationPlanner;
use Pidgey\Infra\Migrations\MigrationResult;
use Pidgey\Infra\Migrations\MigrationRunner;
use Pidgey\Infra\Migrations\MigrationStatus;
use Pidgey\Infra\Migrations\StatementSplitter;
use Pidgey\Infra\PdoFactory;
use Pidgey\Tests\Support\IntegrationTestCase;
use Pidgey\Tests\Support\ThrowawayDatabase;

#[CoversClass(MigrationRunner::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(DatabaseServer::class)]
#[UsesClass(Env::class)]
#[UsesClass(Migration::class)]
#[UsesClass(MigrationLoader::class)]
#[UsesClass(MigrationPlanner::class)]
#[UsesClass(MigrationResult::class)]
#[UsesClass(MigrationStatus::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(StatementSplitter::class)]
final class MigrationRunnerTest extends IntegrationTestCase
{
    private const FIXTURES = __DIR__ . '/../../../fixtures/migrations';

    private ThrowawayDatabase $database;

    private ?string $tempDirectory = null;

    protected function setUp(): void
    {
        $this->database = new ThrowawayDatabase(self::config());
    }

    protected function tearDown(): void
    {
        $this->database->drop();

        if ($this->tempDirectory !== null) {
            array_map('unlink', glob($this->tempDirectory . '/*') ?: []);
            rmdir($this->tempDirectory);
        }
    }

    public function testAppliesEveryMigrationOnFreshDatabase(): void
    {
        $result = $this->runner(self::FIXTURES . '/valid')->migrate();

        $this->assertSame([1, 2], array_map(static fn (Migration $m): int => $m->version, $result->applied));
        $this->assertSame(['gadgets', 'schema_migrations', 'widgets'], $this->tables());
        $this->assertSame(
            [
                [1, 'create_widgets', hash_file('sha256', self::FIXTURES . '/valid/0001_create_widgets.sql')],
                [2, 'create_gadgets', hash_file('sha256', self::FIXTURES . '/valid/0002_create_gadgets.sql')],
            ],
            array_map(
                static fn (array $row): array => [$row['version'], $row['name'], $row['checksum']],
                $this->appliedRows(),
            ),
        );
    }

    public function testSecondRunIsNoop(): void
    {
        $this->runner(self::FIXTURES . '/valid')->migrate();
        $tables = $this->tables();
        $rows = $this->appliedRows();

        $result = $this->runner(self::FIXTURES . '/valid')->migrate();

        $this->assertSame([], $result->applied);
        $this->assertSame($tables, $this->tables());
        $this->assertSame($rows, $this->appliedRows());
    }

    public function testFailingMigrationStopsTheRun(): void
    {
        try {
            $this->runner(self::FIXTURES . '/failing')->migrate();
            $this->fail('Expected a MigrationException');
        } catch (MigrationException $exception) {
            $this->assertStringContainsString('Migration failed: ', $exception->getMessage());
            $this->assertStringContainsString('0002_broken.sql', $exception->getMessage());
        }

        $this->assertSame([1], $this->appliedVersions());
        $this->assertSame(['schema_migrations', 'widgets'], $this->tables());
    }

    public function testEditedAppliedFileFails(): void
    {
        $directory = $this->copyFixture('valid');
        $this->runner($directory)->migrate();
        file_put_contents("{$directory}/0001_create_widgets.sql", "CREATE TABLE widgets (id INT);\n");

        $this->assertMigrateFails($directory, 'checksum mismatch');
        $this->assertSame([1, 2], $this->appliedVersions());
        $this->assertSame(
            [[1, 'checksum_mismatch'], [2, 'applied']],
            $this->statusSummary($directory),
        );
    }

    public function testMissingAppliedFileFailsBeforeApplyingAnything(): void
    {
        $directory = $this->copyFixture('valid');
        $this->runner($directory)->migrate();
        unlink("{$directory}/0001_create_widgets.sql");
        file_put_contents("{$directory}/0003_create_gizmos.sql", "CREATE TABLE gizmos (id INT PRIMARY KEY);\n");

        $this->assertMigrateFails($directory, 'Applied migration 0001_create_widgets has no migration file');
        $this->assertSame([1, 2], $this->appliedVersions());
        $this->assertNotContains('gizmos', $this->tables());
        $this->assertSame(
            [[1, 'missing_file'], [2, 'applied'], [3, 'pending']],
            $this->statusSummary($directory),
        );
    }

    public function testOutOfOrderPendingFileFailsBeforeApplyingAnything(): void
    {
        $directory = $this->copyFixture('gaps');
        $this->runner($directory)->migrate();
        file_put_contents("{$directory}/0002_create_gizmos.sql", "CREATE TABLE gizmos (id INT PRIMARY KEY);\n");

        $this->assertMigrateFails($directory, "{$directory}/0002_create_gizmos.sql");
        $this->assertSame([1, 3], $this->appliedVersions());
        $this->assertNotContains('gizmos', $this->tables());
        $this->assertSame(
            [[1, 'applied'], [2, 'out_of_order'], [3, 'applied']],
            $this->statusSummary($directory),
        );
    }

    #[DataProvider('invalidFixtures')]
    public function testInvalidFilesLeaveDatabaseUntouched(string $case, string $message): void
    {
        $this->assertMigrateFails(self::FIXTURES . "/{$case}", $message);
        $this->assertSame([], $this->appliedVersions());
        $this->assertSame(['schema_migrations'], $this->tables());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidFixtures(): array
    {
        return [
            'duplicate versions' => ['duplicate_versions', 'Duplicate migration versions'],
            'bad name' => ['bad_name', '2_Create-Gadgets.sql'],
            'empty file' => ['empty_file', '0001_empty.sql'],
            'comments only' => ['comments_only', '0001_comments.sql'],
            'multi statement' => ['multi_statement', '0001_two_tables.sql'],
            'missing directory' => ['does_not_exist', 'Migrations directory not found'],
        ];
    }

    public function testEmptyDirectoryIsNoop(): void
    {
        $this->assertSame([], $this->runner(self::FIXTURES . '/empty_dir')->migrate()->applied);
        $this->assertSame(['schema_migrations'], $this->tables());
    }

    public function testHeldLockTimesOutWithoutApplyingAnything(): void
    {
        $runner = $this->runner(self::FIXTURES . '/valid', 1);
        $other = $this->database->newConnection();
        $statement = $other->prepare('SELECT GET_LOCK(:name, 0)');
        $statement->execute(['name' => $runner->lockName()]);
        $this->assertSame(1, (int) $statement->fetchColumn());

        try {
            $runner->migrate();
            $this->fail('Expected a MigrationException');
        } catch (MigrationException $exception) {
            $this->assertStringContainsString(
                "another migration run holds the lock on {$this->database->name()}",
                $exception->getMessage(),
            );
        }

        $this->assertSame([], $this->tables());
    }

    public function testLockIsReleasedAfterRun(): void
    {
        $runner = $this->runner(self::FIXTURES . '/failing');

        try {
            $runner->migrate();
        } catch (MigrationException) {
            // Expected: 0002 is broken.
        }

        $statement = $this->database->newConnection()->prepare('SELECT IS_FREE_LOCK(:name)');
        $statement->execute(['name' => $runner->lockName()]);

        $this->assertSame(1, (int) $statement->fetchColumn());
    }

    public function testStatusWithoutTableReportsPendingAndCreatesNothing(): void
    {
        $this->assertSame(
            [[1, 'pending'], [2, 'pending']],
            $this->statusSummary(self::FIXTURES . '/valid'),
        );
        $this->assertSame([], $this->tables());
    }

    public function testStatusAfterPartialRun(): void
    {
        $directory = $this->copyFixture('valid');
        unlink("{$directory}/0002_create_gadgets.sql");
        $this->runner($directory)->migrate();
        copy(self::FIXTURES . '/valid/0002_create_gadgets.sql', "{$directory}/0002_create_gadgets.sql");

        $this->assertSame([[1, 'applied'], [2, 'pending']], $this->statusSummary($directory));
    }

    public function testLockNameStaysWithinMysqlLimit(): void
    {
        $runner = new MigrationRunner($this->database->pdo(), str_repeat('a', 64), self::FIXTURES . '/valid', 1);

        $this->assertLessThanOrEqual(64, strlen($runner->lockName()));
        $this->assertStringStartsWith('pidgey_migrations:', $runner->lockName());
        $this->assertSame(
            "pidgey_migrations:{$this->database->name()}",
            $this->runner(self::FIXTURES . '/valid')->lockName(),
        );
    }

    private function runner(string $directory, int $lockTimeout = 5): MigrationRunner
    {
        return new MigrationRunner($this->database->pdo(), $this->database->name(), $directory, $lockTimeout);
    }

    private function assertMigrateFails(string $directory, string $message): void
    {
        try {
            $this->runner($directory)->migrate();
            $this->fail('Expected a MigrationException');
        } catch (MigrationException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function copyFixture(string $case): string
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pidgey_migrations_' . bin2hex(random_bytes(6));
        mkdir($this->tempDirectory);

        foreach (glob(self::FIXTURES . "/{$case}/*.sql") ?: [] as $file) {
            copy($file, $this->tempDirectory . '/' . basename($file));
        }

        return $this->tempDirectory;
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name',
        );
        $statement->execute();

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function appliedRows(): array
    {
        if (!in_array('schema_migrations', $this->tables(), true)) {
            return [];
        }

        $statement = $this->database->pdo()->prepare(
            'SELECT version, name, checksum, applied_at FROM schema_migrations ORDER BY version',
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @return list<int>
     */
    private function appliedVersions(): array
    {
        return array_map(static fn (array $row): int => (int) $row['version'], $this->appliedRows());
    }

    /**
     * @return list<array{int, string}>
     */
    private function statusSummary(string $directory): array
    {
        return array_map(
            static fn (MigrationStatus $status): array => [$status->version, $status->state->value],
            $this->runner($directory)->status(),
        );
    }
}
