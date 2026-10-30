<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use LogicException;
use PDO;
use Throwable;

/**
 * Runs a unit of work in a database transaction: begin, commit, or roll back on any error.
 *
 * Transactions are not nested: running one while another is active on the same connection
 * is a programmer error.
 */
final class Transaction
{
    /**
     * Wraps the connection whose transactions this helper runs.
     *
     * @param PDO $pdo The request's connection.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Runs the work inside a transaction and returns its result.
     *
     * The transaction is committed when the work returns. On any `Throwable` it is rolled back
     * (when still active) and the original exception is rethrown unchanged, even if the
     * rollback itself fails.
     *
     * @template T
     *
     * @param callable $work The unit of work.
     *
     * @phpstan-param callable(): T $work
     *
     * @return T
     *
     * @throws LogicException When a transaction is already active on the connection.
     * @throws Throwable      Whatever the work (or the commit) throws.
     */
    public function run(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw new LogicException('A transaction is already active; transactions are not nested.');
        }

        $this->pdo->beginTransaction();

        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->rollBackQuietly();

            throw $exception;
        }
    }

    /**
     * Rolls back the active transaction, ignoring a failing rollback so the original error wins.
     *
     * @return void
     */
    private function rollBackQuietly(): void
    {
        try {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } catch (Throwable) {
            // The original exception is more useful than the rollback failure.
        }
    }
}
