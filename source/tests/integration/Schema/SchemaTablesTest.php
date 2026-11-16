<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Schema;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Tests\Support\SchemaTestCase;

#[CoversNothing]
final class SchemaTablesTest extends SchemaTestCase
{
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function collationProvider(): array
    {
        return [
            'organizations' => [
                'organizations',
                self::COLLATION,
            ],
            'users' => [
                'users',
                self::COLLATION,
            ],
            'refresh_tokens' => [
                'refresh_tokens',
                self::COLLATION,
            ],
            'password_reset_tokens' => [
                'password_reset_tokens',
                self::COLLATION,
            ],
            'authorization_requests' => [
                'authorization_requests',
                self::COLLATION,
            ],
            'account_edit_lockouts' => [
                'account_edit_lockouts',
                self::COLLATION,
            ],
            'api_tokens' => [
                'api_tokens',
                self::COLLATION,
            ],
            'smtp_connections' => [
                'smtp_connections',
                self::COLLATION,
            ],
            'smtp_test_limits' => [
                'smtp_test_limits',
                self::COLLATION,
            ],
        ];
    }

    #[DataProvider('collationProvider')]
    public function testCollation(string $table, string $expected): void
    {
        $this->assertSame($expected, $this->collation($table));
    }
}
