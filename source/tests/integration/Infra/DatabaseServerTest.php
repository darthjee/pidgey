<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\DatabaseServer;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\SystemClock;
use Pidgey\Tests\Support\IntegrationTestCase;

#[CoversClass(DatabaseServer::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(SystemClock::class)]
final class DatabaseServerTest extends IntegrationTestCase
{
    private string $name;

    private DatabaseServer $server;

    protected function setUp(): void
    {
        $this->name = 'pidgey_server_' . bin2hex(random_bytes(6)) . '_test';
        $this->server = new DatabaseServer(new PdoFactory(), self::config());
    }

    protected function tearDown(): void
    {
        $this->server->dropDatabase($this->name);
    }

    public function testWaitUntilReadyReturnsAgainstLiveServer(): void
    {
        $this->server->waitUntilReady(5);

        $this->addToAssertionCount(1);
    }

    public function testCreateAndDropDatabaseAsApplicationUser(): void
    {
        $this->server->createDatabase($this->name);
        $this->assertTrue($this->exists());

        $this->server->createDatabase($this->name);
        $this->assertTrue($this->exists());

        $this->server->dropDatabase($this->name);
        $this->assertFalse($this->exists());

        $this->server->dropDatabase($this->name);
        $this->assertFalse($this->exists());
    }

    public function testCreatedDatabaseUsesUtf8mb4(): void
    {
        $this->server->createDatabase($this->name);

        $statement = self::pdo()->prepare(
            'SELECT default_character_set_name, default_collation_name'
            . ' FROM information_schema.schemata WHERE schema_name = :name',
        );
        $statement->execute(['name' => $this->name]);

        $this->assertSame(
            ['DEFAULT_CHARACTER_SET_NAME' => 'utf8mb4', 'DEFAULT_COLLATION_NAME' => 'utf8mb4_0900_ai_ci'],
            array_change_key_case($statement->fetch(), CASE_UPPER),
        );
    }

    private function exists(): bool
    {
        $statement = self::pdo()->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = :name');
        $statement->execute(['name' => $this->name]);

        return (int) $statement->fetchColumn() === 1;
    }
}
