<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\Row;
use Pidgey\Model\SmtpTestLimit;
use Pidgey\Repository\Sql;
use Pidgey\Repository\SmtpTestLimitRepository;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(SmtpTestLimitRepository::class)]
#[CoversClass(SmtpTestLimit::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(Sql::class)]
final class SmtpTestLimitRepositoryTest extends RepositoryTestCase
{
    private SmtpTestLimitRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new SmtpTestLimitRepository($this->pdo);
    }

    public function testTheFirstCallCreatesTheRow(): void
    {
        $organizationId = $this->fixtures->organization();

        $limit = $this->repository->lockForOrganization($organizationId, self::at('2026-03-01 10:00:00'));

        $this->assertEquals(self::at('2026-03-01 10:00:00'), $limit->windowStartedAt);
        $this->assertSame(0, $limit->attempts);
        $this->assertNull($limit->busyUntil);
        $this->assertSame(1, $this->rowCount($organizationId));
    }

    public function testASecondCallKeepsTheRowAndDoesNotDuplicateIt(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->repository->lockForOrganization($organizationId, self::at('2026-03-01 10:00:00'));
        $this->repository->takeAttempt(
            $organizationId,
            self::at('2026-03-01 10:00:00'),
            1,
            self::at('2026-03-01 10:00:30'),
        );

        $limit = $this->repository->lockForOrganization($organizationId, self::at('2026-03-01 11:00:00'));

        $this->assertEquals(self::at('2026-03-01 10:00:00'), $limit->windowStartedAt);
        $this->assertSame(1, $limit->attempts);
        $this->assertEquals(self::at('2026-03-01 10:00:30'), $limit->busyUntil);
        $this->assertSame(1, $this->rowCount($organizationId));
    }

    public function testAnUnknownOrganizationFails(): void
    {
        $organizationId = $this->fixtures->organization();

        $this->expectException(PDOException::class);

        $this->repository->lockForOrganization($organizationId + 1000000, self::at('2026-03-01 10:00:00'));
    }

    public function testReleaseClearsTheLeaseItHolds(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->repository->lockForOrganization($organizationId, self::at('2026-03-01 10:00:00'));
        $this->repository->takeAttempt(
            $organizationId,
            self::at('2026-03-01 10:00:00'),
            1,
            self::at('2026-03-01 10:00:30'),
        );

        $this->repository->releaseLease($organizationId, self::at('2026-03-01 10:00:30'));

        $limit = $this->repository->lockForOrganization($organizationId, self::at('2026-03-01 10:00:00'));
        $this->assertNull($limit->busyUntil);
        $this->assertSame(1, $limit->attempts);
    }

    public function testReleaseLeavesANewerLeaseAlone(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->repository->lockForOrganization($organizationId, self::at('2026-03-01 10:00:00'));
        $this->repository->takeAttempt(
            $organizationId,
            self::at('2026-03-01 10:00:00'),
            2,
            self::at('2026-03-01 10:01:30'),
        );

        $this->repository->releaseLease($organizationId, self::at('2026-03-01 10:00:30'));

        $limit = $this->repository->lockForOrganization($organizationId, self::at('2026-03-01 10:00:00'));
        $this->assertEquals(self::at('2026-03-01 10:01:30'), $limit->busyUntil);
    }

    private function rowCount(int $organizationId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM smtp_test_limits WHERE organization_id = :id');
        $statement->execute(['id' => $organizationId]);

        return (int) $statement->fetchColumn();
    }

    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }
}
