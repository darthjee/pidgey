<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Infra;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Tests\Support\IntegrationTestCase;

#[CoversClass(PdoFactory::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
final class PdoFactoryTest extends IntegrationTestCase
{
    public function testConnectsToConfiguredDatabase(): void
    {
        $this->assertSame(self::config()->name, self::pdo()->query('SELECT DATABASE()')->fetchColumn());
    }

    public function testServerConnectionSelectsNoDatabase(): void
    {
        $pdo = (new PdoFactory())->createServer(self::config());

        $this->assertNull($pdo->query('SELECT DATABASE()')->fetchColumn());
    }

    public function testCharsetIsUtf8mb4(): void
    {
        $this->assertSame('utf8mb4', self::pdo()->query('SELECT @@character_set_connection')->fetchColumn());
    }

    public function testQueryRejectsStackedStatements(): void
    {
        $this->expectException(PDOException::class);

        self::pdo()->query('SELECT 1; SELECT 2');
    }

    public function testExecRejectsStackedStatements(): void
    {
        $this->expectException(PDOException::class);

        self::pdo()->exec('SELECT 1; SELECT 2');
    }
}
