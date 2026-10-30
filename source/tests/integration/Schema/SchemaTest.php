<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Schema;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use Pidgey\Tests\Support\IntegrationTestCase;

/**
 * Checks that the migrated test database matches the table specs, through information_schema.
 *
 * Each table's implementation adds its name to TABLES and its expectations (columns, types,
 * nullability, defaults, unique and foreign keys, collation) as new test methods. Read-only,
 * so it needs no isolation.
 */
#[CoversNothing]
final class SchemaTest extends IntegrationTestCase
{
    /** Every base table the migrations create, plus the runner's own table. */
    private const TABLES = [
        'schema_migrations',
    ];

    public function testTableSet(): void
    {
        $tables = self::pdo()->query(
            'SELECT table_name FROM information_schema.tables '
            . "WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name",
        )->fetchAll(PDO::FETCH_COLUMN);

        $expected = self::TABLES;
        sort($expected);

        $this->assertSame($expected, $tables);
    }
}
