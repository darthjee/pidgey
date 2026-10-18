<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PDO;
use Pdo\Mysql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\PdoFactory;

#[CoversClass(PdoFactory::class)]
#[UsesClass(DatabaseConfig::class)]
final class PdoFactoryTest extends TestCase
{
    private DatabaseConfig $config;

    protected function setUp(): void
    {
        $this->config = new DatabaseConfig('db.example', 3307, 'pidgey_test', 'user', 'secret');
    }

    public function testDsnWithDatabase(): void
    {
        $this->assertSame(
            'mysql:host=db.example;port=3307;dbname=pidgey_test;charset=utf8mb4',
            (new PdoFactory())->dsn($this->config, true),
        );
    }

    public function testDsnWithoutDatabase(): void
    {
        $this->assertSame(
            'mysql:host=db.example;port=3307;charset=utf8mb4',
            (new PdoFactory())->dsn($this->config, false),
        );
    }

    public function testOptionsHardenTheConnection(): void
    {
        $options = (new PdoFactory())->options();

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
        $this->assertSame(PDO::FETCH_ASSOC, $options[PDO::ATTR_DEFAULT_FETCH_MODE]);
        $this->assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
        $this->assertFalse($options[Mysql::ATTR_MULTI_STATEMENTS]);
    }
}
