<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use InvalidArgumentException;
use PDOException;
use RuntimeException;

/**
 * Server-level MySQL operations: waiting for the server, creating and dropping databases.
 */
final class DatabaseServer
{
    private const RETRY_INTERVAL_SECONDS = 1.0;
    private const NAME_PATTERN = '/^[A-Za-z0-9_]{1,64}$/';

    /**
     * Builds the helper.
     *
     * @param ConnectionFactory $factory Opens server connections.
     * @param DatabaseConfig    $config  Connection settings.
     * @param Clock             $clock   Time source used by the retry loop.
     */
    public function __construct(
        private readonly ConnectionFactory $factory,
        private readonly DatabaseConfig $config,
        private readonly Clock $clock = new SystemClock(),
    ) {
    }

    /**
     * Blocks until the MySQL server accepts connections.
     *
     * @param integer $timeoutSeconds Maximum time to wait.
     *
     * @return void
     *
     * @throws RuntimeException When the server does not accept connections in time.
     */
    public function waitUntilReady(int $timeoutSeconds): void
    {
        $deadline = $this->clock->now() + $timeoutSeconds;

        while (true) {
            try {
                $this->factory->createServer($this->config);

                return;
            } catch (PDOException $exception) {
                $lastError = $exception->getMessage();
            }

            if ($this->clock->now() >= $deadline) {
                throw new RuntimeException(
                    "MySQL at {$this->config->host}:{$this->config->port} did not accept connections"
                    . " within {$timeoutSeconds}s: {$lastError}",
                );
            }

            $this->clock->sleep(self::RETRY_INTERVAL_SECONDS);
        }
    }

    /**
     * Creates the database when it does not exist.
     *
     * @param string $name Database name.
     *
     * @return void
     *
     * @throws InvalidArgumentException When the name is not a safe identifier.
     */
    public function createDatabase(string $name): void
    {
        $sql = 'CREATE DATABASE IF NOT EXISTS ' . $this->quote($name)
            . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci';

        $this->factory->createServer($this->config)->exec($sql);
    }

    /**
     * Drops the database when it exists.
     *
     * @param string $name Database name.
     *
     * @return void
     *
     * @throws InvalidArgumentException When the name is not a safe identifier.
     */
    public function dropDatabase(string $name): void
    {
        $sql = 'DROP DATABASE IF EXISTS ' . $this->quote($name);

        $this->factory->createServer($this->config)->exec($sql);
    }

    /**
     * Validates a database name and backtick-quotes it.
     *
     * @param string $name Database name.
     *
     * @return string
     *
     * @throws InvalidArgumentException When the name is not a safe identifier.
     */
    private function quote(string $name): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException("Invalid database name: {$name}");
        }

        return "`{$name}`";
    }
}
