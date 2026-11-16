<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Schema;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Tests\Support\SchemaTestCase;

#[CoversNothing]
final class SchemaColumnsTest extends SchemaTestCase
{
    /**
     * @return array<string, array{string, array<string, array<string, mixed>>}>
     */
    public static function columnsProvider(): array
    {
        return [
            'organizations' => [
                'organizations',
                [
                    'id' => self::ID,
                    'name' => self::VARCHAR_255,
                    'created_at' => self::CREATED_AT,
                    'updated_at' => self::UPDATED_AT,
                ],
            ],
            'users' => [
                'users',
                [
                    'id' => self::ID,
                    'organization_id' => self::BIGINT,
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
            ],
            'refresh_tokens' => [
                'refresh_tokens',
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
            ],
            'password_reset_tokens' => [
                'password_reset_tokens',
                [
                    'id' => self::ID,
                    'token_hash' => self::CHAR_64,
                    'user_id' => self::BIGINT,
                    'created_at' => self::CREATED_AT,
                    'expires_at' => self::DATETIME,
                    'used_at' => self::NULLABLE_DATETIME,
                ],
            ],
            'authorization_requests' => [
                'authorization_requests',
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
            ],
            'account_edit_lockouts' => [
                'account_edit_lockouts',
                [
                    'id' => self::ID,
                    'user_id' => self::BIGINT,
                    'failed_attempts' => self::COUNTER,
                    'locked_until' => self::NULLABLE_DATETIME,
                    'created_at' => self::CREATED_AT,
                    'updated_at' => self::UPDATED_AT,
                ],
            ],
            'api_tokens' => [
                'api_tokens',
                [
                    'id' => self::ID,
                    'organization_id' => self::BIGINT,
                    'name' => self::VARCHAR_255,
                    'token_prefix' => ['type' => 'varchar(16)', 'nullable' => false, 'default' => null, 'extra' => ''],
                    'token_hash' => self::CHAR_64,
                    'scopes' => ['type' => 'json', 'nullable' => false, 'default' => null, 'extra' => ''],
                    'created_at' => self::CREATED_AT,
                    'last_used_at' => self::NULLABLE_DATETIME,
                    'revoked_at' => self::NULLABLE_DATETIME,
                ],
            ],
            'smtp_connections' => [
                'smtp_connections',
                [
                    'id' => self::ID,
                    'organization_id' => self::BIGINT,
                    'name' => self::VARCHAR_255,
                    'host' => self::VARCHAR_255,
                    'port' => ['type' => 'int', 'nullable' => false, 'default' => null, 'extra' => ''],
                    'encryption' => [
                        'type' => "enum('none','tls','ssl')",
                        'nullable' => false,
                        'default' => null,
                        'extra' => '',
                    ],
                    'username' => ['type' => 'varchar(255)', 'nullable' => true, 'default' => null, 'extra' => ''],
                    'password' => ['type' => 'varbinary(2048)', 'nullable' => true, 'default' => null, 'extra' => ''],
                    'from_address' => self::VARCHAR_255,
                    'active' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => '0', 'extra' => ''],
                    'created_at' => self::CREATED_AT,
                    'updated_at' => self::UPDATED_AT,
                    'active_organization_id' => [
                        'type' => 'bigint',
                        'nullable' => true,
                        'default' => null,
                        'extra' => 'STORED GENERATED',
                    ],
                ],
            ],
            'smtp_test_limits' => [
                'smtp_test_limits',
                [
                    'id' => self::ID,
                    'organization_id' => self::BIGINT,
                    'window_started_at' => self::DATETIME,
                    'attempts' => self::COUNTER,
                    'busy_until' => self::NULLABLE_DATETIME,
                    'created_at' => self::CREATED_AT,
                    'updated_at' => self::UPDATED_AT,
                ],
            ],
        ];
    }

    #[DataProvider('columnsProvider')]
    public function testColumns(string $table, array $expected): void
    {
        $this->assertSame($expected, $this->columns($table));
    }

    public function testUsersStatusHasNoDefault(): void
    {
        $columns = $this->columns('users');

        $this->assertNull($columns['status']['default']);
    }

    public function testSmtpConnectionsActiveOrganizationIdIsGeneratedFromActive(): void
    {
        $statement = self::pdo()->prepare(
            'SELECT generation_expression FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
        );
        $statement->execute(['table' => 'smtp_connections', 'column' => 'active_organization_id']);
        $expression = strtolower((string) $statement->fetchColumn());

        $this->assertStringContainsString('if(', str_replace(' ', '', $expression));
        $this->assertStringContainsString('`active`', $expression);
        $this->assertStringContainsString('`organization_id`', $expression);
        $this->assertStringContainsString('null', $expression);
    }
}
