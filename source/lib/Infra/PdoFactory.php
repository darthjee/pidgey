<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use PDO;
use Pdo\Mysql;

/**
 * Creates hardened PDO connections to MySQL.
 *
 * Every connection throws on errors, fetches associative arrays, uses native prepared
 * statements and refuses stacked (multi-statement) queries.
 */
final class PdoFactory implements ConnectionFactory
{
    /**
     * Opens a connection to the configured database.
     *
     * @param DatabaseConfig $config Connection settings.
     *
     * @return PDO
     */
    public function create(DatabaseConfig $config): PDO
    {
        return new PDO($this->dsn($config, true), $config->user, $config->password, $this->options());
    }

    /**
     * Opens a connection to the MySQL server without selecting a database.
     *
     * @param DatabaseConfig $config Connection settings.
     *
     * @return PDO
     */
    public function createServer(DatabaseConfig $config): PDO
    {
        return new PDO($this->dsn($config, false), $config->user, $config->password, $this->options());
    }

    /**
     * Builds the pdo_mysql DSN.
     *
     * @param DatabaseConfig $config       Connection settings.
     * @param boolean        $withDatabase Whether to select the configured database.
     *
     * @return string
     */
    public function dsn(DatabaseConfig $config, bool $withDatabase): string
    {
        $dsn = "mysql:host={$config->host};port={$config->port}";

        if ($withDatabase) {
            $dsn .= ";dbname={$config->name}";
        }

        return $dsn . ';charset=utf8mb4';
    }

    /**
     * Returns the PDO options applied to every connection.
     *
     * @return array<int, mixed>
     */
    public function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            Mysql::ATTR_MULTI_STATEMENTS => false,
        ];
    }
}
