<?php

declare(strict_types=1);

namespace Pidgey;

use PDO;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\Transaction;
use RuntimeException;

/**
 * Composition root: builds the services of one request through typed, lazy, memoized getters.
 *
 * Nothing is opened until a getter is called, so a request that never touches the database
 * never connects (and needs no DB_* variables).
 */
final class Container
{
    private ?PDO $pdo = null;

    private ?Transaction $transaction = null;

    /**
     * Creates an empty container; nothing is built yet.
     *
     * @param ConnectionFactory $connectionFactory Opens the database connection (swappable in tests).
     */
    public function __construct(private readonly ConnectionFactory $connectionFactory = new PdoFactory())
    {
    }

    /**
     * Returns the request's database connection, opening it on the first call.
     *
     * @return PDO
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connectionFactory->create(DatabaseConfig::fromEnv());
    }

    /**
     * Returns the transaction helper bound to the request's connection.
     *
     * @return Transaction
     *
     * @throws RuntimeException When a required DB_* variable is missing or invalid.
     */
    public function transaction(): Transaction
    {
        return $this->transaction ??= new Transaction($this->pdo());
    }
}
