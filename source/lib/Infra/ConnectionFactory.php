<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use PDO;

/**
 * Opens database connections.
 */
interface ConnectionFactory
{
    /**
     * Opens a connection to the configured database.
     *
     * @param DatabaseConfig $config Connection settings.
     *
     * @return PDO
     */
    public function create(DatabaseConfig $config): PDO;

    /**
     * Opens a connection to the MySQL server without selecting a database.
     *
     * @param DatabaseConfig $config Connection settings.
     *
     * @return PDO
     */
    public function createServer(DatabaseConfig $config): PDO;
}
