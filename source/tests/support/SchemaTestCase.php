<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PDO;

/**
 * Base class for the schema tests: checks that the migrated test database matches the table
 * specs, through information_schema. Read-only, so it needs no isolation.
 *
 * Each table's implementation adds its name to TABLES and its expectations (columns, types,
 * nullability, defaults, unique and foreign keys, collation) as new provider rows.
 */
abstract class SchemaTestCase extends IntegrationTestCase
{

    /** Every base table the migrations create, plus the runner's own table. */
    protected const TABLES = [
        'account_edit_lockouts',
        'api_tokens',
        'authorization_requests',
        'organizations',
        'password_reset_tokens',
        'refresh_tokens',
        'schema_migrations',
        'smtp_connections',
        'smtp_test_limits',
        'users',
    ];

    protected const COLLATION = 'utf8mb4_0900_ai_ci';

    protected const ID = ['type' => 'bigint', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment'];

    protected const CREATED_AT = [
        'type' => 'datetime',
        'nullable' => false,
        'default' => 'CURRENT_TIMESTAMP',
        'extra' => 'DEFAULT_GENERATED',
    ];

    protected const UPDATED_AT = [
        'type' => 'datetime',
        'nullable' => false,
        'default' => 'CURRENT_TIMESTAMP',
        'extra' => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP',
    ];

    protected const VARCHAR_255 = ['type' => 'varchar(255)', 'nullable' => false, 'default' => null, 'extra' => ''];

    protected const CHAR_64 = ['type' => 'char(64)', 'nullable' => false, 'default' => null, 'extra' => ''];

    protected const BIGINT = ['type' => 'bigint', 'nullable' => false, 'default' => null, 'extra' => ''];

    protected const NULLABLE_BIGINT = ['type' => 'bigint', 'nullable' => true, 'default' => null, 'extra' => ''];

    protected const DATETIME = ['type' => 'datetime', 'nullable' => false, 'default' => null, 'extra' => ''];

    protected const NULLABLE_DATETIME = ['type' => 'datetime', 'nullable' => true, 'default' => null, 'extra' => ''];

    protected const COUNTER = ['type' => 'int', 'nullable' => false, 'default' => '0', 'extra' => ''];

    protected const ORGANIZATION_FK = [
        'column' => 'organization_id',
        'referenced_table' => 'organizations',
        'referenced_column' => 'id',
        'delete_rule' => 'RESTRICT',
    ];

    protected const USER_FK = [
        'column' => 'user_id',
        'referenced_table' => 'users',
        'referenced_column' => 'id',
        'delete_rule' => 'RESTRICT',
    ];

    /**
     * Columns of a table, in declaration order.
     *
     * @return array<string, array{type: string, nullable: bool, default: ?string, extra: string}>
     */
    protected function columns(string $table): array
    {
        $statement = self::pdo()->prepare(
            'SELECT column_name AS name, column_type AS type, is_nullable AS nullable, '
            . 'column_default AS `default`, extra AS extra '
            . 'FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position',
        );
        $statement->execute(['table' => $table]);
        $columns = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[$row['name']] = [
                'type' => $row['type'],
                'nullable' => $row['nullable'] === 'YES',
                'default' => $row['default'],
                'extra' => $row['extra'],
            ];
        }

        return $columns;
    }

    /**
     * Unique keys of a table (primary key excluded), by name, with their columns in order.
     *
     * @return array<string, list<string>>
     */
    protected function uniqueKeys(string $table): array
    {
        return $this->indexColumns($table, true);
    }

    /**
     * Plain (non-unique) indexes of a table, by name, with their columns in order.
     *
     * @return array<string, list<string>>
     */
    protected function indexes(string $table): array
    {
        return $this->indexColumns($table, false);
    }

    /**
     * Foreign keys of a table, by constraint name (single-column keys).
     *
     * @return array<string, array{
     *     column: string,
     *     referenced_table: string,
     *     referenced_column: string,
     *     delete_rule: string,
     * }>
     */
    protected function foreignKeys(string $table): array
    {
        $statement = self::pdo()->prepare(
            'SELECT k.constraint_name AS name, k.column_name AS `column`, '
            . 'k.referenced_table_name AS referenced_table, k.referenced_column_name AS referenced_column, '
            . 'r.delete_rule AS delete_rule '
            . 'FROM information_schema.key_column_usage k '
            . 'JOIN information_schema.referential_constraints r '
            . 'ON r.constraint_schema = k.constraint_schema AND r.constraint_name = k.constraint_name '
            . 'AND r.table_name = k.table_name '
            . 'WHERE k.table_schema = DATABASE() AND k.table_name = :table '
            . 'AND k.referenced_table_name IS NOT NULL '
            . 'ORDER BY k.constraint_name, k.ordinal_position',
        );
        $statement->execute(['table' => $table]);
        $keys = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $keys[$row['name']] = [
                'column' => $row['column'],
                'referenced_table' => $row['referenced_table'],
                'referenced_column' => $row['referenced_column'],
                'delete_rule' => $row['delete_rule'],
            ];
        }

        return $keys;
    }

    protected function collation(string $table): string
    {
        $statement = self::pdo()->prepare(
            'SELECT table_collation FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name = :table',
        );
        $statement->execute(['table' => $table]);

        return (string) $statement->fetchColumn();
    }

    /**
     * @return array<string, list<string>>
     */
    protected function indexColumns(string $table, bool $unique): array
    {
        $statement = self::pdo()->prepare(
            'SELECT index_name AS name, column_name AS `column` FROM information_schema.statistics '
            . "WHERE table_schema = DATABASE() AND table_name = :table AND index_name <> 'PRIMARY' "
            . 'AND non_unique = :non_unique ORDER BY index_name, seq_in_index',
        );
        $statement->execute(['table' => $table, 'non_unique' => $unique ? 0 : 1]);
        $indexes = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $indexes[$row['name']][] = $row['column'];
        }

        return $indexes;
    }
}
