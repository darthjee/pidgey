<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Support;

use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversNothing;
use Pidgey\Infra\DatabaseServer;
use Pidgey\Infra\PdoFactory;
use Pidgey\Tests\Support\DatabaseCleaner;
use Pidgey\Tests\Support\IntegrationTestCase;
use Pidgey\Tests\Support\ThrowawayDatabase;

/**
 * Runs the cleaner against a throwaway `_test` database holding scratch tables.
 */
#[CoversNothing]
final class DatabaseCleanerTest extends IntegrationTestCase
{
    private ThrowawayDatabase $database;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->database = new ThrowawayDatabase(self::config());
        $this->pdo = $this->database->pdo();
        $this->pdo->exec('CREATE TABLE schema_migrations (version INT UNSIGNED NOT NULL PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE parents (id INT AUTO_INCREMENT PRIMARY KEY)');
        $this->pdo->exec(
            'CREATE TABLE `child-rows` (id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT NOT NULL, '
            . 'FOREIGN KEY (parent_id) REFERENCES parents (id) ON DELETE RESTRICT)',
        );
        $this->pdo->exec('CREATE VIEW parent_ids AS SELECT id FROM parents');
        $this->pdo->exec('INSERT INTO schema_migrations (version) VALUES (1)');
        $this->pdo->exec('INSERT INTO parents (id) VALUES (1), (2)');
        $this->pdo->exec('INSERT INTO `child-rows` (parent_id) VALUES (1), (2)');
    }

    protected function tearDown(): void
    {
        $this->database->drop();
    }

    public function testEmptiesEveryApplicationTable(): void
    {
        DatabaseCleaner::truncateAll($this->pdo);

        $this->assertSame(0, $this->rowCount('parents'));
        $this->assertSame(0, $this->rowCount('child-rows'));
    }

    public function testResetsAutoIncrement(): void
    {
        DatabaseCleaner::truncateAll($this->pdo);
        $this->pdo->exec('INSERT INTO parents () VALUES ()');

        $this->assertSame(1, (int) $this->pdo->query('SELECT MAX(id) FROM parents')->fetchColumn());
    }

    public function testKeepsSchemaMigrations(): void
    {
        DatabaseCleaner::truncateAll($this->pdo);

        $this->assertSame(1, $this->rowCount('schema_migrations'));
    }

    public function testRestoresForeignKeyChecks(): void
    {
        DatabaseCleaner::truncateAll($this->pdo);

        $this->assertForeignKeyChecksOn();
    }

    public function testRestoresForeignKeyChecksWhenATruncateFails(): void
    {
        $locker = $this->database->newConnection();
        $locker->beginTransaction();
        $locker->query('SELECT COUNT(*) FROM parents')->fetchColumn();
        $this->pdo->exec('SET SESSION lock_wait_timeout = 1');

        try {
            DatabaseCleaner::truncateAll($this->pdo);
            $this->fail('Expected the truncate to time out.');
        } catch (PDOException) {
            $this->assertForeignKeyChecksOn();
        } finally {
            $locker->rollBack();
        }
    }

    public function testRefusesADatabaseNotEndingInTest(): void
    {
        $factory = new PdoFactory();
        $server = new DatabaseServer($factory, self::config());
        $config = self::config()->withName('pidgey_cleaner_' . bin2hex(random_bytes(6)));
        $server->createDatabase($config->name);

        try {
            $pdo = $factory->create($config);
            $pdo->exec('CREATE TABLE keep_me (id INT PRIMARY KEY)');
            $pdo->exec('INSERT INTO keep_me (id) VALUES (1)');

            try {
                DatabaseCleaner::truncateAll($pdo);
                $this->fail('Expected the cleaner to refuse.');
            } catch (LogicException) {
                $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM keep_me')->fetchColumn());
            }
        } finally {
            $server->dropDatabase($config->name);
        }
    }

    public function testRefusesAConnectionWithoutDatabase(): void
    {
        $this->expectException(LogicException::class);

        DatabaseCleaner::truncateAll((new PdoFactory())->createServer(self::config()));
    }

    private function rowCount(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    private function assertForeignKeyChecksOn(): void
    {
        $this->assertSame(1, (int) $this->pdo->query('SELECT @@session.foreign_key_checks')->fetchColumn());
    }
}
