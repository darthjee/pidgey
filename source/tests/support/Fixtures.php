<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PDO;

/**
 * Creates rows for integration tests with plain SQL (never through the repository under test).
 *
 * Each table's implementation adds one method (`organization()`, `user()`, …) taking named
 * overrides, filling the rest with valid defaults (unique values for unique columns) and
 * returning the new id. A fixture creates the rows it depends on.
 */
final class Fixtures
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * A value unique to this run, e.g. `user-3f9a1c0b2d4e`, for unique columns.
     */
    public function unique(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(6));
    }

    /**
     * Inserts one row and returns its `AUTO_INCREMENT` id.
     *
     * `$table` and the keys of `$values` are identifiers and must be literals from the fixture
     * source; the values are bound as named parameters.
     *
     * @param array<string, mixed> $values Column name => value.
     */
    public function insert(string $table, array $values): int
    {
        $columns = array_keys($values);
        $statement = $this->pdo->prepare(
            "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . '`) '
            . 'VALUES (:' . implode(', :', $columns) . ')',
        );
        $statement->execute($values);

        return (int) $this->pdo->lastInsertId();
    }
}
