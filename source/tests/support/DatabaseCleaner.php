<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use LogicException;
use PDO;

/**
 * Empties every application table, for integration tests of code that owns its transactions
 * (and so cannot run inside a rolled-back test transaction).
 *
 * Tables are discovered from `information_schema`, so new tables need no list to maintain.
 * `schema_migrations` is never touched. Refuses to run unless the connected database ends in
 * `_test`, on top of the guard in `IntegrationTestCase`.
 */
final class DatabaseCleaner
{
    private const TABLES_SQL = <<<'SQL'
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_type = 'BASE TABLE'
          AND table_name <> 'schema_migrations'
        ORDER BY table_name
        SQL;

    /**
     * Truncates every application table of the connected database.
     *
     * @param PDO $pdo Connection to a `_test` database.
     *
     * @return void
     *
     * @throws LogicException When the connected database does not end in `_test`.
     */
    public static function truncateAll(PDO $pdo): void
    {
        $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

        if (!is_string($database) || !str_ends_with($database, '_test')) {
            throw new LogicException('Refusing to truncate: the connected database must end in _test.');
        }

        $tables = $pdo->query(self::TABLES_SQL)->fetchAll(PDO::FETCH_COLUMN);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                $pdo->exec('TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`');
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
