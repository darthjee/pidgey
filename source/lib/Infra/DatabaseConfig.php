<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use RuntimeException;

/**
 * Database connection settings.
 */
final readonly class DatabaseConfig
{
    private const DEFAULT_PORT = '3306';

    /**
     * Builds the settings from explicit values.
     *
     * @param string  $host     MySQL server host name.
     * @param integer $port     MySQL server port.
     * @param string  $name     Database name.
     * @param string  $user     User name.
     * @param string  $password Password (may be empty).
     */
    public function __construct(
        public string $host,
        public int $port,
        public string $name,
        public string $user,
        public string $password,
    ) {
    }

    /**
     * Reads the settings from the DB_* environment variables.
     *
     * @return self
     *
     * @throws RuntimeException When a required variable is missing or DB_PORT is invalid.
     */
    public static function fromEnv(): self
    {
        return new self(
            Env::required('DB_HOST'),
            self::parsePort(Env::get('DB_PORT', self::DEFAULT_PORT) ?? self::DEFAULT_PORT),
            Env::required('DB_NAME'),
            Env::required('DB_USER'),
            Env::get('DB_PASSWORD', '') ?? '',
        );
    }

    /**
     * Reads the settings used by the schema commands (migrate, create-db, drop-db).
     *
     * Uses DB_MIGRATE_USER / DB_MIGRATE_PASSWORD when DB_MIGRATE_USER is set, and falls
     * back to the DB_* settings of fromEnv() otherwise; host, port and name are shared.
     *
     * @return self
     *
     * @throws RuntimeException When a required variable is missing or DB_PORT is invalid.
     */
    public static function forMigrations(): self
    {
        $config = self::fromEnv();
        $user = Env::get('DB_MIGRATE_USER');

        if ($user === null) {
            return $config;
        }

        return new self(
            $config->host,
            $config->port,
            $config->name,
            $user,
            Env::get('DB_MIGRATE_PASSWORD', '') ?? '',
        );
    }

    /**
     * Returns a copy of the settings pointing at another database.
     *
     * @param string $name Database name.
     *
     * @return self
     */
    public function withName(string $name): self
    {
        return new self($this->host, $this->port, $name, $this->user, $this->password);
    }

    /**
     * Parses DB_PORT as a positive integer.
     *
     * @param string $value Raw value.
     *
     * @return integer
     *
     * @throws RuntimeException When the value is not a positive integer.
     */
    private static function parsePort(string $value): int
    {
        if (preg_match('/^[0-9]+$/', $value) !== 1 || (int) $value < 1 || (int) $value > 65535) {
            throw new RuntimeException("Invalid DB_PORT: {$value}");
        }

        return (int) $value;
    }
}
