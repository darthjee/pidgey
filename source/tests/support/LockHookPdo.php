<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Closure;
use PDO;
use PDOStatement;

/**
 * A PDO that runs a hook once, right before the first `SELECT … FOR UPDATE` is prepared, so a
 * test can change a row deterministically between a listing and the lock.
 */
final class LockHookPdo extends PDO
{
    private ?Closure $beforeLock = null;

    /**
     * Sets the hook run once before the next `FOR UPDATE` statement is prepared.
     *
     * @param Closure $hook Closure(): void.
     */
    public function beforeNextLock(Closure $hook): void
    {
        $this->beforeLock = $hook;
    }

    /**
     * Runs the pending hook when the statement locks, then prepares it.
     *
     * @param string            $query   The statement.
     * @param array<int, mixed> $options Driver options.
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->beforeLock !== null && str_contains($query, 'FOR UPDATE')) {
            $hook = $this->beforeLock;
            $this->beforeLock = null;
            $hook();
        }

        return parent::prepare($query, $options);
    }
}
