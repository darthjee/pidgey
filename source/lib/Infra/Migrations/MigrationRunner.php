<?php

declare(strict_types=1);

namespace Pidgey\Infra\Migrations;

use PDO;
use PDOException;

/**
 * Applies pending migrations to a database and reports their status.
 *
 * Runs are serialized per database with a MySQL named lock (GET_LOCK), which MySQL releases
 * automatically when the session ends.
 */
final class MigrationRunner implements Migrator
{
    private const LOCK_PREFIX = 'pidgey_migrations:';
    private const LOCK_NAME_MAX_LENGTH = 64;

    private const CREATE_TABLE_SQL = <<<'SQL'
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version INT UNSIGNED NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            checksum CHAR(64) NOT NULL,
            applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL;

    /**
     * Builds the runner.
     *
     * @param PDO              $pdo          Connection to the target database.
     * @param string           $databaseName Name of the target database (used for the lock name).
     * @param string           $directory    Migrations directory.
     * @param integer          $lockTimeout  Seconds to wait for the migrations lock.
     * @param MigrationLoader  $loader       Reads and validates the migration files.
     * @param MigrationPlanner $planner      Compares the files with the applied state.
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $databaseName,
        private readonly string $directory,
        private readonly int $lockTimeout,
        private readonly MigrationLoader $loader = new MigrationLoader(),
        private readonly MigrationPlanner $planner = new MigrationPlanner(),
    ) {
    }

    /**
     * Applies every pending migration, in version order.
     *
     * @return MigrationResult
     *
     * @throws MigrationException When the lock cannot be acquired, validation fails or a migration fails.
     */
    public function migrate(): MigrationResult
    {
        $this->acquireLock();

        try {
            $this->pdo->exec(self::CREATE_TABLE_SQL);
            $pending = $this->planner->pending($this->appliedRows(), $this->loader->load($this->directory));

            foreach ($pending as $migration) {
                $this->apply($migration);
            }

            return new MigrationResult($pending);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Reports the status of every migration without changing anything (takes no lock).
     *
     * @return list<MigrationStatus>
     *
     * @throws MigrationException When the migration files are invalid.
     */
    public function status(): array
    {
        $applied = $this->tableExists() ? $this->appliedRows() : [];

        return $this->planner->statuses($applied, $this->loader->load($this->directory));
    }

    /**
     * Returns the named lock used for the target database, at most 64 characters long.
     *
     * @return string
     */
    public function lockName(): string
    {
        $name = self::LOCK_PREFIX . $this->databaseName;

        if (strlen($name) > self::LOCK_NAME_MAX_LENGTH) {
            return self::LOCK_PREFIX . sha1($this->databaseName);
        }

        return $name;
    }

    /**
     * Acquires the migrations lock for the target database.
     *
     * @return void
     *
     * @throws MigrationException When the lock is not acquired within the timeout.
     */
    private function acquireLock(): void
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(:name, :timeout)');
        $statement->execute(['name' => $this->lockName(), 'timeout' => $this->lockTimeout]);
        $acquired = $statement->fetchColumn();

        if ($acquired === null || (int) $acquired !== 1) {
            throw new MigrationException(
                "Timed out after {$this->lockTimeout}s: another migration run holds the lock on {$this->databaseName}",
            );
        }
    }

    /**
     * Releases the migrations lock.
     *
     * @return void
     */
    private function releaseLock(): void
    {
        $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
        $statement->execute(['name' => $this->lockName()]);
    }

    /**
     * Tells whether the schema_migrations table exists in the current database.
     *
     * @return boolean
     */
    private function tableExists(): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables'
            . ' WHERE table_schema = DATABASE() AND table_name = :table',
        );
        $statement->execute(['table' => 'schema_migrations']);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Reads the applied migrations.
     *
     * @return array<int, array{name: string, checksum: string}> Keyed by version.
     */
    private function appliedRows(): array
    {
        $statement = $this->pdo->prepare('SELECT version, name, checksum FROM schema_migrations ORDER BY version');
        $statement->execute();

        $applied = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $applied[(int) $row['version']] = [
                'name' => (string) $row['name'],
                'checksum' => (string) $row['checksum'],
            ];
        }

        return $applied;
    }

    /**
     * Runs one migration and records it.
     *
     * @param Migration $migration Migration to apply.
     *
     * @return void
     *
     * @throws MigrationException When the statement fails (the migration is not recorded).
     */
    private function apply(Migration $migration): void
    {
        try {
            $this->pdo->exec($migration->sql);
        } catch (PDOException $exception) {
            throw new MigrationException(
                "Migration failed: {$migration->path}: {$exception->getMessage()}",
                0,
                $exception,
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO schema_migrations (version, name, checksum) VALUES (:version, :name, :checksum)',
        );
        $statement->execute([
            'version' => $migration->version,
            'name' => $migration->name,
            'checksum' => $migration->checksum,
        ]);
    }
}
