<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Schema;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Tests\Support\SchemaTestCase;

#[CoversNothing]
final class SchemaKeysTest extends SchemaTestCase
{
    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function uniqueKeysProvider(): array
    {
        return [
            'organizations' => [
                'organizations',
                [],
            ],
            'users' => [
                'users',
                [
                    'uq_users_email' => ['email'],
                    'uq_users_username' => ['username'],
                ],
            ],
            'refresh_tokens' => [
                'refresh_tokens',
                ['uq_refresh_tokens_token_hash' => ['token_hash']],
            ],
            'password_reset_tokens' => [
                'password_reset_tokens',
                ['uq_password_reset_tokens_token_hash' => ['token_hash']],
            ],
            'authorization_requests' => [
                'authorization_requests',
                [
                    'uq_authorization_requests_poll_token_hash' => ['poll_token_hash'],
                    'uq_authorization_requests_uuid' => ['uuid'],
                ],
            ],
            'account_edit_lockouts' => [
                'account_edit_lockouts',
                ['uq_account_edit_lockouts_user_id' => ['user_id']],
            ],
            'api_tokens' => [
                'api_tokens',
                ['uq_api_tokens_token_hash' => ['token_hash']],
            ],
            'smtp_connections' => [
                'smtp_connections',
                ['uq_smtp_connections_active_organization_id' => ['active_organization_id']],
            ],
            'smtp_test_limits' => [
                'smtp_test_limits',
                ['uq_smtp_test_limits_organization_id' => ['organization_id']],
            ],
        ];
    }

    #[DataProvider('uniqueKeysProvider')]
    public function testUniqueKeys(string $table, array $expected): void
    {
        $this->assertSame($expected, $this->uniqueKeys($table));
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function indexesProvider(): array
    {
        return [
            'organizations' => [
                'organizations',
                [],
            ],
            'users' => [
                'users',
                ['ix_users_organization_id' => ['organization_id']],
            ],
            'refresh_tokens' => [
                'refresh_tokens',
                [
                    'ix_refresh_tokens_session_uuid' => ['session_uuid'],
                    'ix_refresh_tokens_user_id' => ['user_id'],
                ],
            ],
            'password_reset_tokens' => [
                'password_reset_tokens',
                ['ix_password_reset_tokens_user_id' => ['user_id']],
            ],
            'authorization_requests' => [
                'authorization_requests',
                [
                    'ix_authorization_requests_approved_by_user_id' => ['approved_by_user_id'],
                    'ix_authorization_requests_expires_at' => ['expires_at'],
                    'ix_authorization_requests_request_ip_created_at' => ['request_ip', 'created_at'],
                    'ix_authorization_requests_user_id_status' => ['user_id', 'status'],
                    'ix_authorization_requests_username_created_at' => ['username', 'created_at'],
                ],
            ],
            'account_edit_lockouts' => [
                'account_edit_lockouts',
                [],
            ],
            'api_tokens' => [
                'api_tokens',
                ['ix_api_tokens_organization_id' => ['organization_id']],
            ],
            'smtp_connections' => [
                'smtp_connections',
                ['ix_smtp_connections_organization_id' => ['organization_id']],
            ],
            'smtp_test_limits' => [
                'smtp_test_limits',
                [],
            ],
        ];
    }

    #[DataProvider('indexesProvider')]
    public function testIndexes(string $table, array $expected): void
    {
        $this->assertSame($expected, $this->indexes($table));
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function foreignKeysProvider(): array
    {
        return [
            'organizations' => [
                'organizations',
                [],
            ],
            'users' => [
                'users',
                ['fk_users_organization_id' => self::ORGANIZATION_FK],
            ],
            'refresh_tokens' => [
                'refresh_tokens',
                ['fk_refresh_tokens_user_id' => self::USER_FK],
            ],
            'password_reset_tokens' => [
                'password_reset_tokens',
                ['fk_password_reset_tokens_user_id' => self::USER_FK],
            ],
            'authorization_requests' => [
                'authorization_requests',
                [
                    'fk_authorization_requests_approved_by_user_id' => [
                        'column' => 'approved_by_user_id',
                        'referenced_table' => 'users',
                        'referenced_column' => 'id',
                        'delete_rule' => 'RESTRICT',
                    ],
                    'fk_authorization_requests_user_id' => self::USER_FK,
                ],
            ],
            'account_edit_lockouts' => [
                'account_edit_lockouts',
                ['fk_account_edit_lockouts_user_id' => self::USER_FK],
            ],
            'api_tokens' => [
                'api_tokens',
                ['fk_api_tokens_organization_id' => self::ORGANIZATION_FK],
            ],
            'smtp_connections' => [
                'smtp_connections',
                ['fk_smtp_connections_organization_id' => self::ORGANIZATION_FK],
            ],
            'smtp_test_limits' => [
                'smtp_test_limits',
                ['fk_smtp_test_limits_organization_id' => self::ORGANIZATION_FK],
            ],
        ];
    }

    #[DataProvider('foreignKeysProvider')]
    public function testForeignKeys(string $table, array $expected): void
    {
        $this->assertSame($expected, $this->foreignKeys($table));
    }
}
