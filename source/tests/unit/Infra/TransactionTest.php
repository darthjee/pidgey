<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Transaction;
use RuntimeException;

#[CoversClass(Transaction::class)]
final class TransactionTest extends TestCase
{
    public function testCommitsAndReturnsTheResult(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('commit')->willReturn(true);
        $pdo->expects($this->never())->method('rollBack');

        $this->assertSame('done', (new Transaction($pdo))->run(static fn (): string => 'done'));
    }

    public function testRollsBackAndRethrowsTheSameException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false, true);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->once())->method('rollBack')->willReturn(true);
        $error = new RuntimeException('boom');

        try {
            (new Transaction($pdo))->run(static fn () => throw $error);
            $this->fail('Expected the work exception to be rethrown.');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }

    public function testRethrowsTheOriginalExceptionWhenRollBackFails(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false, true);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('rollBack')->willThrowException(new PDOException('gone away'));
        $error = new RuntimeException('boom');

        try {
            (new Transaction($pdo))->run(static fn () => throw $error);
            $this->fail('Expected the work exception to be rethrown.');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }

    public function testDoesNotRollBackWhenNoTransactionIsActiveAnymore(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false, false);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('rollBack');
        $error = new RuntimeException('boom');

        $this->expectExceptionObject($error);

        (new Transaction($pdo))->run(static fn () => throw $error);
    }

    public function testRollsBackWhenCommitFails(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false, true);
        $pdo->method('beginTransaction')->willReturn(true);
        $failure = new PDOException('deadlock');
        $pdo->method('commit')->willThrowException($failure);
        $pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $this->expectExceptionObject($failure);

        (new Transaction($pdo))->run(static fn (): int => 1);
    }

    public function testRejectsNesting(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('beginTransaction');
        $ran = false;

        try {
            (new Transaction($pdo))->run(static function () use (&$ran): void {
                $ran = true;
            });
            $this->fail('Expected nesting to be rejected.');
        } catch (LogicException) {
            $this->assertFalse($ran);
        }
    }
}
