<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\AccountEditLockout;
use Pidgey\Model\Row;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(AccountEditLockoutRepository::class)]
#[UsesClass(AccountEditLockout::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
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
}
