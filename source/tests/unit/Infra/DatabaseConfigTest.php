<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use RuntimeException;

#[CoversClass(DatabaseConfig::class)]
#[CoversClass(Env::class)]
final class DatabaseConfigTest extends TestCase
{
    private const VARIABLES = [
        'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
        'DB_MIGRATE_USER', 'DB_MIGRATE_PASSWORD',
    ];

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
        }

        putenv('DB_HOST=db.example');
        putenv('DB_PORT=3307');
        putenv('DB_NAME=pidgey_example_test');
        putenv('DB_USER=someone');
        putenv('DB_PASSWORD=secret');
        putenv('DB_MIGRATE_USER');
        putenv('DB_MIGRATE_PASSWORD');
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    public function testFromEnvReadsEveryVariable(): void
    {
        $config = DatabaseConfig::fromEnv();

        $this->assertSame('db.example', $config->host);
        $this->assertSame(3307, $config->port);
        $this->assertSame('pidgey_example_test', $config->name);
        $this->assertSame('someone', $config->user);
        $this->assertSame('secret', $config->password);
    }

    public function testFromEnvDefaultsPort(): void
    {
        putenv('DB_PORT');

        $this->assertSame(3306, DatabaseConfig::fromEnv()->port);
    }

    public function testFromEnvDefaultsPasswordToEmpty(): void
    {
        putenv('DB_PASSWORD');

        $this->assertSame('', DatabaseConfig::fromEnv()->password);
    }

    #[DataProvider('requiredVariables')]
    public function testFromEnvThrowsOnMissingRequiredVariable(string $name): void
    {
        putenv($name);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Missing required environment variable: {$name}");

        DatabaseConfig::fromEnv();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredVariables(): array
    {
        return [
            'host' => ['DB_HOST'],
            'name' => ['DB_NAME'],
            'user' => ['DB_USER'],
        ];
    }

    #[DataProvider('invalidPorts')]
    public function testFromEnvThrowsOnInvalidPort(string $port): void
    {
        putenv("DB_PORT={$port}");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Invalid DB_PORT: {$port}");

        DatabaseConfig::fromEnv();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPorts(): array
    {
        return [
            'text' => ['abc'],
            'zero' => ['0'],
            'negative' => ['-1'],
            'too large' => ['65536'],
            'decimal' => ['33.06'],
        ];
    }

    public function testForMigrationsFallsBackToAppCredentialsWhenUnset(): void
    {
        $config = DatabaseConfig::forMigrations();

        $this->assertSame('someone', $config->user);
        $this->assertSame('secret', $config->password);
    }

    public function testForMigrationsFallsBackWhenMigrateUserIsEmpty(): void
    {
        putenv('DB_MIGRATE_USER=');
        putenv('DB_MIGRATE_PASSWORD=ignored');

        $config = DatabaseConfig::forMigrations();

        $this->assertSame('someone', $config->user);
        $this->assertSame('secret', $config->password);
    }

    public function testForMigrationsUsesMigrationCredentialsWhenSet(): void
    {
        putenv('DB_MIGRATE_USER=migrator');
        putenv('DB_MIGRATE_PASSWORD=schema-secret');

        $config = DatabaseConfig::forMigrations();

        $this->assertSame('migrator', $config->user);
        $this->assertSame('schema-secret', $config->password);
    }

    public function testForMigrationsDefaultsPasswordToEmpty(): void
    {
        putenv('DB_MIGRATE_USER=migrator');

        $this->assertSame('', DatabaseConfig::forMigrations()->password);
    }

    public function testForMigrationsSharesHostPortAndName(): void
    {
        putenv('DB_MIGRATE_USER=migrator');
        putenv('DB_MIGRATE_PASSWORD=schema-secret');

        $config = DatabaseConfig::forMigrations();

        $this->assertSame('db.example', $config->host);
        $this->assertSame(3307, $config->port);
        $this->assertSame('pidgey_example_test', $config->name);
    }

    public function testFromEnvIgnoresMigrationCredentials(): void
    {
        putenv('DB_MIGRATE_USER=migrator');
        putenv('DB_MIGRATE_PASSWORD=schema-secret');

        $config = DatabaseConfig::fromEnv();

        $this->assertSame('someone', $config->user);
        $this->assertSame('secret', $config->password);
    }

    public function testWithNameReplacesOnlyTheName(): void
    {
        $config = new DatabaseConfig('h', 1, 'a', 'u', 'p');
        $other = $config->withName('b');

        $this->assertSame('b', $other->name);
        $this->assertSame('a', $config->name);
        $this->assertSame('h', $other->host);
        $this->assertSame(1, $other->port);
        $this->assertSame('u', $other->user);
        $this->assertSame('p', $other->password);
    }
}
