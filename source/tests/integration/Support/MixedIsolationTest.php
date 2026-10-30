<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Support;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Depends;
use Pidgey\Tests\Support\DatabaseCleaner;
use Pidgey\Tests\Support\RepositoryTestCase;

/**
 * Proves that rollback-based isolation still holds after a truncating test in the same run.
 *
 * Tests run in declaration order: the first truncates `pidgey_test` (on its own connection),
 * the next ones rely on the per-test transaction.
 */
#[CoversNothing]
final class MixedIsolationTest extends RepositoryTestCase
{
    private const PROBE_VERSION = 4294967295;

    public function testTruncatingStep(): void
    {
        $other = self::pdo();

        DatabaseCleaner::truncateAll($other);

        $this->assertSame(0, $this->applicationRowCount($other));
    }

    #[Depends('testTruncatingStep')]
    public function testRollbackStepStartsInsideATransaction(): void
    {
        $this->assertTrue($this->pdo->inTransaction());
        $this->assertSame(0, $this->applicationRowCount($this->pdo));

        $this->pdo->prepare('INSERT INTO schema_migrations (version, name, checksum) VALUES (:v, :n, :c)')
            ->execute(['v' => self::PROBE_VERSION, 'n' => 'isolation_probe', 'c' => str_repeat('0', 64)]);

        $this->assertSame(1, $this->probeCount($this->pdo));
        $this->assertSame(0, $this->probeCount(self::pdo()));
    }

    #[Depends('testRollbackStepStartsInsideATransaction')]
    public function testRollbackStepLeftNothingBehind(): void
    {
        $this->assertTrue($this->pdo->inTransaction());
        $this->assertSame(0, $this->probeCount($this->pdo));
    }

    private function probeCount(PDO $pdo): int
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version = :v');
        $statement->execute(['v' => self::PROBE_VERSION]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Total rows across every application table (all tables but schema_migrations).
     */
    private function applicationRowCount(PDO $pdo): int
    {
        $tables = $pdo->query(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() '
            . "AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations'",
        )->fetchAll(PDO::FETCH_COLUMN);
        $total = 0;

        foreach ($tables as $table) {
            $total += (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        }

        return $total;
    }
}
