<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PDO;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\DatabaseServer;
use Pidgey\Infra\PdoFactory;

/**
 * A uniquely named `pidgey_runner_<hex>_test` database, created on demand and dropped by
 * `drop()` (call it from tearDown so it also runs when a test fails).
 */
final class ThrowawayDatabase
{
    public readonly DatabaseConfig $config;

    private readonly PdoFactory $factory;

    private readonly DatabaseServer $server;

    private ?PDO $pdo = null;

    public function __construct(DatabaseConfig $base)
    {
        $this->factory = new PdoFactory();
        $this->config = $base->withName('pidgey_runner_' . bin2hex(random_bytes(6)) . '_test');
        $this->server = new DatabaseServer($this->factory, $base);
        $this->server->createDatabase($this->config->name);
    }

    public function name(): string
    {
        return $this->config->name;
    }

    /**
     * Shared connection to the throwaway database.
     */
    public function pdo(): PDO
    {
        return $this->pdo ??= $this->factory->create($this->config);
    }

    /**
     * A new, independent connection to the throwaway database.
     */
    public function newConnection(): PDO
    {
        return $this->factory->create($this->config);
    }

    public function drop(): void
    {
        $this->pdo = null;
        $this->server->dropDatabase($this->config->name);
    }
}
