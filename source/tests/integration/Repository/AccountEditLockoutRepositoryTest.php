<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\AccountEditLockout;
use Pidgey\Model\Row;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\Sql;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(AccountEditLockoutRepository::class)]
#[UsesClass(AccountEditLockout::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(Sql::class)]
final class AccountEditLockoutRepositoryTest extends RepositoryTestCase
{
    private AccountEditLockoutRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new AccountEditLockoutRepository($this->pdo);
    }

    public function testCreateReturnsTheDefaults(): void
    {
        $userId = $this->fixtures->user();

        $lockout = $this->repository->create($userId);

        $this->assertGreaterThan(0, $lockout->id);
        $this->assertSame($userId, $lockout->userId);
        $this->assertSame(0, $lockout->failedAttempts);
        $this->assertNull($lockout->lockedUntil);
        $this->assertSame('UTC', $lockout->createdAt->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), $lockout->createdAt->getTimestamp(), 60);
        $this->assertEquals($lockout->createdAt, $lockout->updatedAt);
        $this->assertEquals($lockout, $this->repository->findById($lockout->id));
    }

    public function testCreateRejectsAnUnknownUser(): void
    {
        $userId = $this->fixtures->user();

        $this->expectException(PDOException::class);

        $this->repository->create($userId + 1000000);
    }

    public function testCreateRejectsASecondRowForTheSameUser(): void
    {
        $userId = $this->fixtures->user();
        $this->fixtures->accountEditLockout(userId: $userId);

        $this->expectException(PDOException::class);

        $this->repository->create($userId);
    }

    public function testFindByIdReadsALockedRow(): void
    {
        $id = $this->fixtures->accountEditLockout(failedAttempts: 5, lockedUntil: '2026-03-01 10:15:00');

        $lockout = $this->repository->findById($id);

        $this->assertSame(5, $lockout?->failedAttempts);
        $this->assertSame('2026-03-01 10:15:00', $lockout?->lockedUntil?->format('Y-m-d H:i:s'));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $id = $this->fixtures->accountEditLockout();

        $this->assertNull($this->repository->findById($id + 1000000));
    }

    public function testFindByUserIdFindsAFixtureRow(): void
    {
        $userId = $this->fixtures->user();
        $id = $this->fixtures->accountEditLockout(userId: $userId);

        $this->assertSame($id, $this->repository->findByUserId($userId)?->id);
    }

    public function testFindByUserIdReturnsNullForAUserWithoutARow(): void
    {
        $this->fixtures->accountEditLockout();

        $this->assertNull($this->repository->findByUserId($this->fixtures->user()));
    }

    public function testEnsureCreatesAMissingRow(): void
    {
        $userId = $this->fixtures->user();

        $this->repository->ensure($userId);

        $lockout = $this->repository->findByUserId($userId);
        $this->assertSame(0, $lockout?->failedAttempts);
        $this->assertNull($lockout?->lockedUntil);
    }

    public function testEnsureLeavesAnExistingRowUnchanged(): void
    {
        $userId = $this->fixtures->user();
        $id = $this->fixtures->accountEditLockout($userId, 3, '2026-03-01 10:15:00');

        $this->repository->ensure($userId);
        $this->repository->ensure($userId);

        $lockout = $this->repository->findByUserId($userId);
        $this->assertSame($id, $lockout?->id);
        $this->assertSame(3, $lockout?->failedAttempts);
        $this->assertSame('2026-03-01 10:15:00', $lockout?->lockedUntil?->format('Y-m-d H:i:s'));
        $this->assertSame(1, $this->countRows($userId));
    }

    public function testLockForUpdateReadsTheRow(): void
    {
        $userId = $this->fixtures->user();
        $id = $this->fixtures->accountEditLockout($userId, 2);

        $lockout = $this->repository->lockForUpdate($userId);

        $this->assertSame($id, $lockout->id);
        $this->assertSame(2, $lockout->failedAttempts);
    }

    public function testLockForUpdateFailsWithoutARow(): void
    {
        $this->expectException(LogicException::class);

        $this->repository->lockForUpdate($this->fixtures->user());
    }

    public function testClearResetsTheCounterAndTheLock(): void
    {
        $userId = $this->fixtures->user();
        $this->fixtures->accountEditLockout($userId, 7, '2026-03-01 10:15:00');

        $this->repository->clear($userId);

        $lockout = $this->repository->findByUserId($userId);
        $this->assertSame(0, $lockout?->failedAttempts);
        $this->assertNull($lockout?->lockedUntil);
    }

    public function testRecordFailureBelowTheThresholdDoesNotLock(): void
    {
        $userId = $this->fixtures->user();
        $this->fixtures->accountEditLockout($userId, 3);

        $lockout = $this->repository->recordFailure($userId, 5, $this->lockUntil());

        $this->assertSame(4, $lockout->failedAttempts);
        $this->assertNull($lockout->lockedUntil);
    }

    public function testRecordFailureAtTheThresholdLocks(): void
    {
        $userId = $this->fixtures->user();
        $this->fixtures->accountEditLockout($userId, 4);

        $lockout = $this->repository->recordFailure($userId, 5, $this->lockUntil());

        $this->assertSame(5, $lockout->failedAttempts);
        $this->assertSame('2026-03-01 10:20:00', $lockout->lockedUntil?->format('Y-m-d H:i:s'));
    }

    public function testRecordFailureAboveTheThresholdStillLocks(): void
    {
        $userId = $this->fixtures->user();
        $this->fixtures->accountEditLockout($userId, 9);

        $lockout = $this->repository->recordFailure($userId, 5, $this->lockUntil());

        $this->assertSame(10, $lockout->failedAttempts);
        $this->assertSame('2026-03-01 10:20:00', $lockout->lockedUntil?->format('Y-m-d H:i:s'));
    }

    public function testRecordFailureCreatesAMissingRow(): void
    {
        $userId = $this->fixtures->user();

        $lockout = $this->repository->recordFailure($userId, 5, $this->lockUntil());

        $this->assertSame(1, $lockout->failedAttempts);
        $this->assertNull($lockout->lockedUntil);
    }

    public function testRecordFailureOnAMissingRowLocksWithAThresholdOfOne(): void
    {
        $userId = $this->fixtures->user();

        $lockout = $this->repository->recordFailure($userId, 1, $this->lockUntil());

        $this->assertSame(1, $lockout->failedAttempts);
        $this->assertSame('2026-03-01 10:20:00', $lockout->lockedUntil?->format('Y-m-d H:i:s'));
    }

    public function testRecordFailureCountsEveryCall(): void
    {
        $userId = $this->fixtures->user();
        $this->repository->ensure($userId);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->repository->recordFailure($userId, 5, $this->lockUntil());
        }

        $this->assertSame(3, $this->repository->findByUserId($userId)?->failedAttempts);
    }

    private function lockUntil(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-03-01 10:20:00', new DateTimeZone('UTC'));
    }

    private function countRows(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM account_edit_lockouts WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }
}
