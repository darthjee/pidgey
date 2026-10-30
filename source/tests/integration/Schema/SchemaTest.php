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
 * nullability, defaults, unique and foreign keys, collation) as new test methods, using the
 * shared helpers at the bottom. Read-only, so it needs no isolation.
 */
#[CoversNothing]
final class SchemaTest extends IntegrationTestCase
{
    /** Every base table the migrations create, plus the runner's own table. */
    private const TABLES = [
        'account_edit_lockouts',
        'authorization_requests',
        'organizations',
        'password_reset_tokens',
        'refresh_tokens',
        'schema_migrations',
        'users',
    ];

    private const COLLATION = 'utf8mb4_0900_ai_ci';

    private const ID = ['type' => 'bigint', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment'];

    private const CREATED_AT = [
        'type' => 'datetime',
        'nullable' => false,
        'default' => 'CURRENT_TIMESTAMP',
        'extra' => 'DEFAULT_GENERATED',
    ];

    private const UPDATED_AT = [
        'type' => 'datetime',
        'nullable' => false,
        'default' => 'CURRENT_TIMESTAMP',
        'extra' => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP',
    ];

    private const VARCHAR_255 = ['type' => 'varchar(255)', 'nullable' => false, 'default' => null, 'extra' => ''];

    private const CHAR_64 = ['type' => 'char(64)', 'nullable' => false, 'default' => null, 'extra' => ''];

    private const BIGINT = ['type' => 'bigint', 'nullable' => false, 'default' => null, 'extra' => ''];

    private const NULLABLE_BIGINT = ['type' => 'bigint', 'nullable' => true, 'default' => null, 'extra' => ''];

    private const DATETIME = ['type' => 'datetime', 'nullable' => false, 'default' => null, 'extra' => ''];

    private const NULLABLE_DATETIME = ['type' => 'datetime', 'nullable' => true, 'default' => null, 'extra' => ''];

    private const COUNTER = ['type' => 'int', 'nullable' => false, 'default' => '0', 'extra' => ''];

    private const USER_FK = [
        'column' => 'user_id',
        'referenced_table' => 'users',
        'referenced_column' => 'id',
        'delete_rule' => 'RESTRICT',
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

    public function testOrganizationsColumns(): void
    {
        $this->assertSame(
            [
                'id' => self::ID,
                'name' => self::VARCHAR_255,
                'created_at' => self::CREATED_AT,
                'updated_at' => self::UPDATED_AT,
            ],
            $this->columns('organizations'),
        );
    }

    public function testOrganizationsKeys(): void
    {
        $this->assertSame([], $this->uniqueKeys('organizations'));
        $this->assertSame([], $this->indexes('organizations'));
        $this->assertSame([], $this->foreignKeys('organizations'));
    }

    public function testOrganizationsCollation(): void
    {
        $this->assertSame(self::COLLATION, $this->collation('organizations'));
    }

    public function testUsersColumns(): void
    {
        $this->assertSame(
            [
                'id' => self::ID,
                'organization_id' => ['type' => 'bigint', 'nullable' => false, 'default' => null, 'extra' => ''],
                'username' => self::VARCHAR_255,
                'email' => self::VARCHAR_255,
                'password_digest' => self::VARCHAR_255,
                'is_admin' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => '0', 'extra' => ''],
                'status' => [
                    'type' => "enum('pending','approved','denied')",
                    'nullable' => false,
                    'default' => null,
                    'extra' => '',
                ],
                'created_at' => self::CREATED_AT,
                'updated_at' => self::UPDATED_AT,
            ],
            $this->columns('users'),
        );
    }

    public function testUsersStatusHasNoDefault(): void
    {
        $columns = $this->columns('users');

        $this->assertNull($columns['status']['default']);
    }

    public function testUsersUniqueKeys(): void
    {
        $this->assertSame(
            [
                'uq_users_email' => ['email'],
                'uq_users_username' => ['username'],
            ],
            $this->uniqueKeys('users'),
        );
    }

    public function testUsersIndexes(): void
    {
        $this->assertSame(
            ['ix_users_organization_id' => ['organization_id']],
            $this->indexes('users'),
        );
    }

    public function testUsersForeignKeys(): void
    {
        $this->assertSame(
            [
                'fk_users_organization_id' => [
                    'column' => 'organization_id',
                    'referenced_table' => 'organizations',
                    'referenced_column' => 'id',
                    'delete_rule' => 'RESTRICT',
                ],
            ],
            $this->foreignKeys('users'),
        );
    }

    public function testUsersCollation(): void
    {
        $this->assertSame(self::COLLATION, $this->collation('users'));
    }

    public function testRefreshTokensColumns(): void
    {
        $this->assertSame(
            [
                'id' => self::ID,
                'token_hash' => self::CHAR_64,
                'user_id' => self::BIGINT,
                'issued_at' => self::CREATED_AT,
                'expires_at' => self::DATETIME,
                'revoked_at' => self::NULLABLE_DATETIME,
                'revoked_reason' => ['type' => 'varchar(32)', 'nullable' => true, 'default' => null, 'extra' => ''],
                'session_uuid' => ['type' => 'varchar(36)', 'nullable' => false, 'default' => null, 'extra' => ''],
                'started_at' => self::DATETIME,
            ],
            $this->columns('refresh_tokens'),
        );
    }

    public function testRefreshTokensUniqueKeys(): void
    {
        $this->assertSame(
            ['uq_refresh_tokens_token_hash' => ['token_hash']],
            $this->uniqueKeys('refresh_tokens'),
        );
    }

    public function testRefreshTokensIndexes(): void
    {
        $this->assertSame(
            [
                'ix_refresh_tokens_session_uuid' => ['session_uuid'],
                'ix_refresh_tokens_user_id' => ['user_id'],
            ],
            $this->indexes('refresh_tokens'),
        );
    }

    public function testRefreshTokensForeignKeys(): void
    {
        $this->assertSame(
            ['fk_refresh_tokens_user_id' => self::USER_FK],
            $this->foreignKeys('refresh_tokens'),
        );
    }

    public function testRefreshTokensCollation(): void
    {
        $this->assertSame(self::COLLATION, $this->collation('refresh_tokens'));
    }

    public function testPasswordResetTokensColumns(): void
    {
        $this->assertSame(
            [
                'id' => self::ID,
                'token_hash' => self::CHAR_64,
                'user_id' => self::BIGINT,
                'created_at' => self::CREATED_AT,
                'expires_at' => self::DATETIME,
                'used_at' => self::NULLABLE_DATETIME,
            ],
            $this->columns('password_reset_tokens'),
        );
    }

    public function testPasswordResetTokensUniqueKeys(): void
    {
        $this->assertSame(
            ['uq_password_reset_tokens_token_hash' => ['token_hash']],
            $this->uniqueKeys('password_reset_tokens'),
        );
    }

    public function testPasswordResetTokensIndexes(): void
    {
        $this->assertSame(
            ['ix_password_reset_tokens_user_id' => ['user_id']],
            $this->indexes('password_reset_tokens'),
        );
    }

    public function testPasswordResetTokensForeignKeys(): void
    {
        $this->assertSame(
            ['fk_password_reset_tokens_user_id' => self::USER_FK],
            $this->foreignKeys('password_reset_tokens'),
        );
    }

    public function testPasswordResetTokensCollation(): void
    {
        $this->assertSame(self::COLLATION, $this->collation('password_reset_tokens'));
    }

    public function testAuthorizationRequestsColumns(): void
    {
        $this->assertSame(
            [
                'id' => self::ID,
                'uuid' => ['type' => 'varchar(36)', 'nullable' => false, 'default' => null, 'extra' => ''],
                'username' => self::VARCHAR_255,
                'user_id' => self::NULLABLE_BIGINT,
                'status' => [
                    'type' => "enum('open','approved','denied','logged','expired')",
                    'nullable' => false,
                    'default' => 'open',
                    'extra' => '',
                ],
                'poll_token_hash' => self::CHAR_64,
                'request_ip' => ['type' => 'varchar(45)', 'nullable' => false, 'default' => null, 'extra' => ''],
                'request_user_agent' => [
                    'type' => 'varchar(512)',
                    'nullable' => false,
                    'default' => null,
                    'extra' => '',
                ],
                'approved_by_user_id' => self::NULLABLE_BIGINT,
                'created_at' => self::CREATED_AT,
                'expires_at' => self::DATETIME,
                'resolved_at' => self::NULLABLE_DATETIME,
                'logged_at' => self::NULLABLE_DATETIME,
                'authorize_failed_attempts' => self::COUNTER,
                'authorize_locked_until' => self::NULLABLE_DATETIME,
            ],
            $this->columns('authorization_requests'),
        );
    }

    public function testAuthorizationRequestsUniqueKeys(): void
    {
        $this->assertSame(
            [
                'uq_authorization_requests_poll_token_hash' => ['poll_token_hash'],
                'uq_authorization_requests_uuid' => ['uuid'],
            ],
            $this->uniqueKeys('authorization_requests'),
        );
    }

    public function testAuthorizationRequestsIndexes(): void
    {
        $this->assertSame(
            [
                'ix_authorization_requests_approved_by_user_id' => ['approved_by_user_id'],
                'ix_authorization_requests_expires_at' => ['expires_at'],
                'ix_authorization_requests_request_ip_created_at' => ['request_ip', 'created_at'],
                'ix_authorization_requests_user_id_status' => ['user_id', 'status'],
                'ix_authorization_requests_username_created_at' => ['username', 'created_at'],
            ],
            $this->indexes('authorization_requests'),
        );
    }

    public function testAuthorizationRequestsForeignKeys(): void
    {
        $this->assertSame(
            [
                'fk_authorization_requests_approved_by_user_id' => [
                    'column' => 'approved_by_user_id',
                    'referenced_table' => 'users',
                    'referenced_column' => 'id',
                    'delete_rule' => 'RESTRICT',
                ],
                'fk_authorization_requests_user_id' => self::USER_FK,
            ],
            $this->foreignKeys('authorization_requests'),
        );
    }

    public function testAuthorizationRequestsCollation(): void
    {
        $this->assertSame(self::COLLATION, $this->collation('authorization_requests'));
    }

    public function testAccountEditLockoutsColumns(): void
    {
        $this->assertSame(
            [
                'id' => self::ID,
                'user_id' => self::BIGINT,
                'failed_attempts' => self::COUNTER,
                'locked_until' => self::NULLABLE_DATETIME,
                'created_at' => self::CREATED_AT,
                'updated_at' => self::UPDATED_AT,
            ],
            $this->columns('account_edit_lockouts'),
        );
    }

    public function testAccountEditLockoutsUniqueKeys(): void
    {
        $this->assertSame(
            ['uq_account_edit_lockouts_user_id' => ['user_id']],
            $this->uniqueKeys('account_edit_lockouts'),
        );
    }

    public function testAccountEditLockoutsIndexes(): void
    {
        $this->assertSame([], $this->indexes('account_edit_lockouts'));
    }

    public function testAccountEditLockoutsForeignKeys(): void
    {
        $this->assertSame(
            ['fk_account_edit_lockouts_user_id' => self::USER_FK],
            $this->foreignKeys('account_edit_lockouts'),
        );
    }

    public function testAccountEditLockoutsCollation(): void
    {
        $this->assertSame(self::COLLATION, $this->collation('account_edit_lockouts'));
    }

    /**
     * Columns of a table, in declaration order.
     *
     * @return array<string, array{type: string, nullable: bool, default: ?string, extra: string}>
     */
    private function columns(string $table): array
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
    private function uniqueKeys(string $table): array
    {
        return $this->indexColumns($table, true);
    }

    /**
     * Plain (non-unique) indexes of a table, by name, with their columns in order.
     *
     * @return array<string, list<string>>
     */
    private function indexes(string $table): array
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
    private function foreignKeys(string $table): array
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

    private function collation(string $table): string
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
    private function indexColumns(string $table, bool $unique): array
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
