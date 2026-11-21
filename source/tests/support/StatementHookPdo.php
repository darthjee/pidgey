<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Closure;
use PDO;
use PDOStatement;

/**
 * A PDO that runs a hook once, right before the first statement containing a given text is
 * prepared, so a test can change a row deterministically between a read and a write.
 */
final class StatementHookPdo extends PDO
{
    private ?string $needle = null;

    private ?Closure $hook = null;

    /**
     * Sets the hook run once before the next statement containing `$needle` is prepared.
     *
     * @param string  $needle Text the statement must contain.
     * @param Closure $hook   Closure(): void.
     */
    public function beforeNext(string $needle, Closure $hook): void
    {
        $this->needle = $needle;
        $this->hook = $hook;
    }

    /**
     * Runs the pending hook when the statement matches, then prepares it.
     *
     * @param string            $query   The statement.
     * @param array<int, mixed> $options Driver options.
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->hook !== null && $this->needle !== null && str_contains($query, $this->needle)) {
            $hook = $this->hook;
            $this->hook = null;
            $this->needle = null;
            $hook();
        }

        return parent::prepare($query, $options);
    }
}
