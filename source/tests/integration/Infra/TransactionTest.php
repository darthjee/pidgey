<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Infra;

use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\DatabaseServer;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\Transaction;
use Pidgey\Tests\Support\IntegrationTestCase;
use Pidgey\Tests\Support\ThrowawayDatabase;
use RuntimeException;

/**
 * Runs against a throwaway database holding a probe table, so the transactions under test
 * are real (no outer test transaction) and their effects are checked from a second connection.
 */
#[CoversClass(Transaction::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(DatabaseServer::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
final class TransactionTest extends IntegrationTestCase
{
    private ThrowawayDatabase $database;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->database = new ThrowawayDatabase(self::config());
        $this->pdo = $this->database->pdo();
        $this->pdo->exec('CREATE TABLE probes (id INT PRIMARY KEY, label VARCHAR(20) NOT NULL)');
    }

    protected function tearDown(): void
    {
        $this->database->drop();
    }

    public function testCommittedWritesAreVisibleFromAnotherConnection(): void
    {
        $result = (new Transaction($this->pdo))->run(function (): string {
            $this->pdo->exec("INSERT INTO probes (id, label) VALUES (1, 'kept')");

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame(['kept'], $this->labelsSeenFromAnotherConnection());
    }

    public function testFailedWorkIsRolledBackAndRethrown(): void
    {
        $error = new RuntimeException('boom');

        try {
            (new Transaction($this->pdo))->run(function () use ($error): never {
                $this->pdo->exec("INSERT INTO probes (id, label) VALUES (1, 'lost')");

                throw $error;
            });
            $this->fail('Expected the work exception to be rethrown.');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame([], $this->labelsSeenFromAnotherConnection());
    }

    public function testRejectsNesting(): void
    {
        $transaction = new Transaction($this->pdo);

        try {
            $transaction->run(function () use ($transaction): void {
                $this->pdo->exec("INSERT INTO probes (id, label) VALUES (1, 'outer')");
                $transaction->run(static fn (): null => null);
            });
            $this->fail('Expected nesting to be rejected.');
        } catch (LogicException) {
            $this->assertFalse($this->pdo->inTransaction());
        }

        $this->assertSame([], $this->labelsSeenFromAnotherConnection());
    }

    /**
     * @return list<string>
     */
    private function labelsSeenFromAnotherConnection(): array
    {
        return $this->database->newConnection()
            ->query('SELECT label FROM probes ORDER BY id')
            ->fetchAll(PDO::FETCH_COLUMN);
    }
}
