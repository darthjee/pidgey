<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra\Migrations;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Migrations\StatementSplitter;

#[CoversClass(StatementSplitter::class)]
final class StatementSplitterTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('cases')]
    public function testSplit(string $sql, array $expected): void
    {
        $this->assertSame($expected, (new StatementSplitter())->split($sql));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function cases(): array
    {
        return [
            'empty' => ['', []],
            'whitespace only' => [" \n\t ", []],
            'single without semicolon' => ['SELECT 1', ['SELECT 1']],
            'single with semicolon' => ["SELECT 1;\n", ['SELECT 1']],
            'two statements' => ['SELECT 1; SELECT 2;', ['SELECT 1', 'SELECT 2']],
            'empty statements dropped' => [';;SELECT 1;;', ['SELECT 1']],
            'single quotes' => ["SELECT 'a;b'", ["SELECT 'a;b'"]],
            'double quotes' => ['SELECT "a;b"', ['SELECT "a;b"']],
            'backticks' => ['SELECT `a;b`', ['SELECT `a;b`']],
            'backslash escape' => ["SELECT 'a\\';b'; SELECT 2", ["SELECT 'a\\';b'", 'SELECT 2']],
            'doubled quote escape' => ["SELECT 'a'';b'; SELECT 2", ["SELECT 'a'';b'", 'SELECT 2']],
            'doubled backtick escape' => ['SELECT `a``;b`; SELECT 2', ['SELECT `a``;b`', 'SELECT 2']],
            'backslash is literal in backticks' => ['SELECT `a\\`; SELECT 2', ['SELECT `a\\`', 'SELECT 2']],
            'unterminated quote' => ["SELECT 'a;b", ["SELECT 'a;b"]],
            'dash comment' => ["SELECT 1 -- x; y\n;", ["SELECT 1 -- x; y"]],
            'dash comment at end' => ['SELECT 1; --', ['SELECT 1']],
            'double dash without space is code' => ['SELECT 1--1; SELECT 2', ['SELECT 1--1', 'SELECT 2']],
            'hash comment' => ["# a; b\nSELECT 1", ["# a; b\nSELECT 1"]],
            'block comment' => ['SELECT /* ; */ 1', ['SELECT /* ; */ 1']],
            'unterminated block comment' => ['SELECT 1 /* ;', ['SELECT 1 /* ;']],
            'comments only' => ["-- a;\n# b;\n/* c; */;", []],
            'trailing comment chunk dropped' => ["SELECT 1;\n-- done\n", ['SELECT 1']],
        ];
    }
}
