<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra\Migrations;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Migrations\Migration;
use Pidgey\Infra\Migrations\MigrationException;
use Pidgey\Infra\Migrations\MigrationLoader;
use Pidgey\Infra\Migrations\StatementSplitter;

#[CoversClass(MigrationLoader::class)]
#[CoversClass(Migration::class)]
#[UsesClass(StatementSplitter::class)]
final class MigrationLoaderTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../fixtures/migrations';

    public function testLoadsValidMigrationsInOrder(): void
    {
        $directory = self::FIXTURES . '/valid';
        $migrations = (new MigrationLoader())->load($directory);

        $this->assertCount(2, $migrations);

        $this->assertSame(1, $migrations[0]->version);
        $this->assertSame('create_widgets', $migrations[0]->name);
        $this->assertSame($directory . '/0001_create_widgets.sql', $migrations[0]->path);
        $this->assertStringStartsWith('-- Widgets table', $migrations[0]->sql);
        $this->assertStringEndsWith(')', $migrations[0]->sql);
        $this->assertSame(hash_file('sha256', $migrations[0]->path), $migrations[0]->checksum);

        $this->assertSame(2, $migrations[1]->version);
        $this->assertSame('create_gadgets', $migrations[1]->name);
        $this->assertStringStartsWith('CREATE TABLE gadgets', $migrations[1]->sql);
    }

    public function testAllowsGaps(): void
    {
        $migrations = (new MigrationLoader())->load(self::FIXTURES . '/gaps');

        $this->assertSame([1, 3], array_map(static fn (Migration $m): int => $m->version, $migrations));
    }

    public function testIgnoresNonSqlFiles(): void
    {
        $migrations = (new MigrationLoader())->load(self::FIXTURES . '/with_readme');

        $this->assertCount(1, $migrations);
        $this->assertSame('create_widgets', $migrations[0]->name);
    }

    public function testEmptyDirectoryIsEmptyList(): void
    {
        $this->assertSame([], (new MigrationLoader())->load(self::FIXTURES . '/empty_dir'));
    }

    public function testMissingDirectoryFails(): void
    {
        $directory = self::FIXTURES . '/does_not_exist';

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage("Migrations directory not found: {$directory}");

        (new MigrationLoader())->load($directory);
    }

    public function testBadNameFails(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('2_Create-Gadgets.sql');

        (new MigrationLoader())->load(self::FIXTURES . '/bad_name');
    }

    public function testDuplicateVersionsListsAllConflictingFiles(): void
    {
        $directory = self::FIXTURES . '/duplicate_versions';

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage(
            "Duplicate migration versions: {$directory}/0003_create_gadgets.sql, {$directory}/0003_create_gizmos.sql",
        );

        (new MigrationLoader())->load($directory);
    }

    public function testEmptyFileFails(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage(
            'Migration file has no SQL statement: ' . self::FIXTURES . '/empty_file/0001_empty.sql',
        );

        (new MigrationLoader())->load(self::FIXTURES . '/empty_file');
    }

    public function testCommentsOnlyFileFails(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage(
            'Migration file has no SQL statement: ' . self::FIXTURES . '/comments_only/0001_comments.sql',
        );

        (new MigrationLoader())->load(self::FIXTURES . '/comments_only');
    }

    public function testMultiStatementFileFails(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage(
            'Migration file must contain exactly one statement, found 2: '
            . self::FIXTURES . '/multi_statement/0001_two_tables.sql',
        );

        (new MigrationLoader())->load(self::FIXTURES . '/multi_statement');
    }
}
