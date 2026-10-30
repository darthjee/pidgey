<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Support;

use PHPUnit\Framework\Attributes\CoversNothing;
use Pidgey\Tests\Support\Fixtures;
use Pidgey\Tests\Support\IntegrationTestCase;
use Pidgey\Tests\Support\ThrowawayDatabase;

#[CoversNothing]
final class FixturesTest extends IntegrationTestCase
{
    private ThrowawayDatabase $database;

    protected function setUp(): void
    {
        $this->database = new ThrowawayDatabase(self::config());
        $this->database->pdo()->exec(
            'CREATE TABLE widgets (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL UNIQUE)',
        );
    }

    protected function tearDown(): void
    {
        $this->database->drop();
    }

    public function testUniqueValuesDifferAndKeepThePrefix(): void
    {
        $fixtures = new Fixtures($this->database->pdo());
        $first = $fixtures->unique('user');

        $this->assertMatchesRegularExpression('/^user-[0-9a-f]{12}$/', $first);
        $this->assertNotSame($first, $fixtures->unique('user'));
    }

    public function testInsertReturnsTheNewId(): void
    {
        $fixtures = new Fixtures($this->database->pdo());

        $first = $fixtures->insert('widgets', ['name' => 'a']);
        $second = $fixtures->insert('widgets', ['name' => 'b']);

        $this->assertSame($first + 1, $second);
        $this->assertSame(
            'b',
            $this->database->pdo()->query("SELECT name FROM widgets WHERE id = {$second}")->fetchColumn(),
        );
    }
}
