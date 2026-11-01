<?php

declare(strict_types=1);

namespace Pidgey\Infra\Cli;

use Closure;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\DatabaseServer;
use Pidgey\Infra\Env;
use Pidgey\Infra\Migrations\MigrationResult;
use Pidgey\Infra\Migrations\MigrationRunner;
use Pidgey\Infra\Migrations\MigrationStatus;
use Pidgey\Infra\Migrations\Migrator;
use Pidgey\Infra\PdoFactory;
use Throwable;

/**
 * Database commands behind the `bin/` entry points.
 *
 * Every command returns a process exit code: 0 on success, 1 on any failure (message on
 * stderr), 2 on invalid usage. Dependencies are built lazily so that configuration errors are
 * reported like any other failure.
 */
final class DatabaseCommand
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_FAILURE = 1;
    public const EXIT_USAGE = 2;

    private const DEFAULT_WAIT_TIMEOUT = 60;
    private const DEFAULT_LOCK_TIMEOUT = 60;

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * Builds the commands.
     *
     * @param Closure  $configLoader          Closure(): DatabaseConfig, loads the app user's settings.
     * @param Closure  $migrationConfigLoader Closure(): DatabaseConfig, loads the migration user's settings.
     * @param Closure  $serverFactory         Closure(DatabaseConfig): DatabaseServer.
     * @param Closure  $migratorFactory       Closure(DatabaseConfig): Migrator.
     * @param Closure  $waitTimeout           Closure(): int, seconds to wait for the server.
     * @param resource $stdout                Stream for normal output.
     * @param resource $stderr                Stream for error output.
     */
    public function __construct(
        private readonly Closure $configLoader,
        private readonly Closure $migrationConfigLoader,
        private readonly Closure $serverFactory,
        private readonly Closure $migratorFactory,
        private readonly Closure $waitTimeout,
        $stdout,
        $stderr,
    ) {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    /**
     * Builds the commands from the environment (DB_*, DB_MIGRATE_*, DB_WAIT_TIMEOUT, MIGRATE_LOCK_TIMEOUT).
     *
     * wait-for-db connects as the app user (DB_USER); create-db, drop-db and migrate connect as
     * the migration user (DB_MIGRATE_USER, falling back to DB_USER).
     *
     * @param string   $migrationsDirectory Migrations directory.
     * @param resource $stdout              Stream for normal output.
     * @param resource $stderr              Stream for error output.
     *
     * @return self
     */
    public static function fromEnvironment(string $migrationsDirectory, $stdout, $stderr): self
    {
        $factory = new PdoFactory();

        return new self(
            static fn (): DatabaseConfig => DatabaseConfig::fromEnv(),
            static fn (): DatabaseConfig => DatabaseConfig::forMigrations(),
            static fn (DatabaseConfig $config): DatabaseServer => new DatabaseServer($factory, $config),
            static fn (DatabaseConfig $config): Migrator => new MigrationRunner(
                $factory->create($config),
                $config->name,
                $migrationsDirectory,
                Env::integer('MIGRATE_LOCK_TIMEOUT', self::DEFAULT_LOCK_TIMEOUT),
            ),
            static fn (): int => Env::integer('DB_WAIT_TIMEOUT', self::DEFAULT_WAIT_TIMEOUT),
            $stdout,
            $stderr,
        );
    }

    /**
     * Waits until the MySQL server accepts connections.
     *
     * @return integer Exit code.
     */
    public function waitForDb(): int
    {
        return $this->guard(function (): void {
            $config = ($this->configLoader)();
            $this->server($config)->waitUntilReady(($this->waitTimeout)());
            $this->write("MySQL at {$config->host}:{$config->port} is ready.");
        });
    }

    /**
     * Creates the configured database when it does not exist.
     *
     * @return integer Exit code.
     */
    public function createDb(): int
    {
        return $this->guard(function (): void {
            $config = ($this->migrationConfigLoader)();
            $this->server($config)->createDatabase($config->name);
            $this->write("Database {$config->name} is ready.");
        });
    }

    /**
     * Drops the configured database when it exists.
     *
     * @return integer Exit code.
     */
    public function dropDb(): int
    {
        return $this->guard(function (): void {
            $config = ($this->migrationConfigLoader)();
            $this->server($config)->dropDatabase($config->name);
            $this->write("Database {$config->name} dropped.");
        });
    }

    /**
     * Applies pending migrations, or prints their status with `--status`.
     *
     * @param string[] $arguments Command-line arguments, without the script name.
     *
     * @return integer Exit code.
     */
    public function migrate(array $arguments): int
    {
        if ($arguments !== [] && $arguments !== ['--status']) {
            fwrite($this->stderr, "Usage: migrate [--status]\n");

            return self::EXIT_USAGE;
        }

        $showStatus = $arguments === ['--status'];

        return $this->guard(function () use ($showStatus): void {
            $migrator = ($this->migratorFactory)(($this->migrationConfigLoader)());

            if ($showStatus) {
                $this->printStatus($migrator->status());

                return;
            }

            $this->printResult($migrator->migrate());
        });
    }

    /**
     * Runs a command body, turning any exception into an error message and exit code 1.
     *
     * @param Closure $body Command body.
     *
     * @return integer Exit code.
     */
    private function guard(Closure $body): int
    {
        try {
            $body();
        } catch (Throwable $exception) {
            fwrite($this->stderr, $exception->getMessage() . "\n");

            return self::EXIT_FAILURE;
        }

        return self::EXIT_SUCCESS;
    }

    /**
     * Builds the server helper for the settings.
     *
     * @param DatabaseConfig $config Connection settings.
     *
     * @return DatabaseServer
     */
    private function server(DatabaseConfig $config): DatabaseServer
    {
        return ($this->serverFactory)($config);
    }

    /**
     * Prints the migrations applied by a run.
     *
     * @param MigrationResult $result Outcome of the run.
     *
     * @return void
     */
    private function printResult(MigrationResult $result): void
    {
        if ($result->applied === []) {
            $this->write('Nothing to migrate.');

            return;
        }

        foreach ($result->applied as $migration) {
            $this->write('Applied ' . basename($migration->path));
        }
    }

    /**
     * Prints the status table.
     *
     * @param MigrationStatus[] $statuses Status entries.
     *
     * @return void
     */
    private function printStatus(array $statuses): void
    {
        if ($statuses === []) {
            $this->write('No migrations.');

            return;
        }

        $nameWidth = max(4, ...array_map(static fn (MigrationStatus $status): int => strlen($status->name), $statuses));
        $format = "%-7s  %-{$nameWidth}s  %s";

        $this->write(sprintf($format, 'Version', 'Name', 'State'));

        foreach ($statuses as $status) {
            $this->write(sprintf($format, sprintf('%04d', $status->version), $status->name, $status->state->value));
        }
    }

    /**
     * Writes a line to stdout.
     *
     * @param string $line Line to write.
     *
     * @return void
     */
    private function write(string $line): void
    {
        fwrite($this->stdout, $line . "\n");
    }
}
