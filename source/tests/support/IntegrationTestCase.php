<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;

/**
 * Base class for tests that talk to the dockerized MySQL server.
 *
 * Refuses to run unless DB_NAME ends in `_test`, so the suite can never touch the
 * development database.
 */
abstract class IntegrationTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $name = Env::get('DB_NAME') ?? '';

        if (!str_ends_with($name, '_test')) {
            self::fail("Refusing to run integration tests: DB_NAME must end in _test (got '{$name}').");
        }
    }

    protected static function config(): DatabaseConfig
    {
        return DatabaseConfig::fromEnv();
    }

    protected static function pdo(): PDO
    {
        return (new PdoFactory())->create(self::config());
    }
}
