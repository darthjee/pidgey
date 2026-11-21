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
use Pidgey\Model\IdempotencyKey;
use Pidgey\Model\IdempotencyState;
use Pidgey\Model\Row;
use Pidgey\Repository\IdempotencyKeyRepository;
use Pidgey\Repository\Sql;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(IdempotencyKeyRepository::class)]
#[CoversClass(IdempotencyKey::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(Sql::class)]
final class IdempotencyKeyRepositoryTest extends RepositoryTestCase
{
    private const NOW = '2026-03-01 10:00:00';

    private IdempotencyKeyRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new IdempotencyKeyRepository($this->pdo);
    }

    public function testFindReturnsNullForAnUnknownKey(): void
    {
        $organizationId = $this->fixtures->organization();

        $this->assertNull($this->repository->find($organizationId, self::hash('k')));
    }

    public function testReserveInsertsAnInFlightRow(): void
    {
        $organizationId = $this->fixtures->organization();

        $reserved = $this->reserve($organizationId, 'k', 'r', self::NOW);

        $this->assertTrue($reserved);
        $key = $this->repository->find($organizationId, self::hash('k'));
        $this->assertInstanceOf(IdempotencyKey::class, $key);
        $this->assertSame(IdempotencyState::InFlight, $key->state);
        $this->assertSame(self::hash('r'), $key->requestHash);
        $this->assertNull($key->responseStatus);
        $this->assertNull($key->responseBody);
        $this->assertEquals(self::at('2026-03-01 10:05:00'), $key->leaseUntil);
        $this->assertEquals(self::at('2026-03-02 10:00:00'), $key->expiresAt);
    }

    public function testReserveReturnsFalseWhenTheKeyIsLive(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->reserve($organizationId, 'k', 'first', self::NOW);

        $reserved = $this->reserve($organizationId, 'k', 'second', '2026-03-01 10:01:00');

        $this->assertFalse($reserved);
        $key = $this->repository->find($organizationId, self::hash('k'));
        $this->assertInstanceOf(IdempotencyKey::class, $key);
        $this->assertSame(self::hash('first'), $key->requestHash);
        $this->assertSame(1, $this->rowCount($organizationId));
    }

    public function testReserveOverwritesAnExpiredRow(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->reserve($organizationId, 'k', 'old', self::NOW);
        $this->repository->complete($organizationId, self::hash('k'), 200, '{"old":true}');

        $reserved = $this->reserve($organizationId, 'k', 'new', '2026-03-02 10:00:00');

        $this->assertTrue($reserved);
        $key = $this->repository->find($organizationId, self::hash('k'));
        $this->assertInstanceOf(IdempotencyKey::class, $key);
        $this->assertSame(IdempotencyState::InFlight, $key->state);
        $this->assertSame(self::hash('new'), $key->requestHash);
        $this->assertNull($key->responseStatus);
        $this->assertNull($key->responseBody);
        $this->assertEquals(self::at('2026-03-02 10:05:00'), $key->leaseUntil);
        $this->assertEquals(self::at('2026-03-03 10:00:00'), $key->expiresAt);
        $this->assertSame(1, $this->rowCount($organizationId));
    }

    public function testReserveKeepsTheSameKeyOfAnotherOrganizationApart(): void
    {
        $first = $this->fixtures->organization();
        $second = $this->fixtures->organization();
        $this->reserve($first, 'k', 'r', self::NOW);

        $this->assertTrue($this->reserve($second, 'k', 'r', self::NOW));
    }

    public function testReserveFailsForAnUnknownOrganization(): void
    {
        $organizationId = $this->fixtures->organization();

        $this->expectException(PDOException::class);

        $this->reserve($organizationId + 1000000, 'k', 'r', self::NOW);
    }

    public function testCompleteStoresTheAnswer(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->reserve($organizationId, 'k', 'r', self::NOW);

        $this->repository->complete($organizationId, self::hash('k'), 504, '{"error":"x"}');

        $key = $this->repository->find($organizationId, self::hash('k'));
        $this->assertInstanceOf(IdempotencyKey::class, $key);
        $this->assertSame(IdempotencyState::Completed, $key->state);
        $this->assertSame(504, $key->responseStatus);
        $this->assertSame('{"error":"x"}', $key->responseBody);
    }

    public function testReleaseDeletesTheRowAndFreesTheKey(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->reserve($organizationId, 'k', 'r', self::NOW);

        $this->repository->release($organizationId, self::hash('k'));

        $this->assertNull($this->repository->find($organizationId, self::hash('k')));
        $this->assertTrue($this->reserve($organizationId, 'k', 'r', self::NOW));
    }

    public function testReleaseLeavesOtherKeysAndOrganizationsAlone(): void
    {
        $organizationId = $this->fixtures->organization();
        $other = $this->fixtures->organization();
        $this->reserve($organizationId, 'k', 'r', self::NOW);
        $this->reserve($organizationId, 'other', 'r', self::NOW);
        $this->reserve($other, 'k', 'r', self::NOW);

        $this->repository->release($organizationId, self::hash('k'));

        $this->assertSame(1, $this->rowCount($organizationId));
        $this->assertSame(1, $this->rowCount($other));
    }

    public function testDeleteExpiredRemovesOnlyExpiredRowsOfTheOrganization(): void
    {
        $organizationId = $this->fixtures->organization();
        $other = $this->fixtures->organization();
        $this->reserve($organizationId, 'old', 'r', '2026-02-27 10:00:00');
        $this->reserve($organizationId, 'live', 'r', self::NOW);
        $this->reserve($other, 'old', 'r', '2026-02-27 10:00:00');

        $deleted = $this->repository->deleteExpired($organizationId, self::at(self::NOW), 100);

        $this->assertSame(1, $deleted);
        $this->assertNull($this->repository->find($organizationId, self::hash('old')));
        $this->assertInstanceOf(IdempotencyKey::class, $this->repository->find($organizationId, self::hash('live')));
        $this->assertInstanceOf(IdempotencyKey::class, $this->repository->find($other, self::hash('old')));
    }

    public function testDeleteExpiredIsBoundedByTheLimit(): void
    {
        $organizationId = $this->fixtures->organization();
        foreach (['a', 'b', 'c'] as $name) {
            $this->reserve($organizationId, $name, 'r', '2026-02-27 10:00:00');
        }

        $deleted = $this->repository->deleteExpired($organizationId, self::at(self::NOW), 2);

        $this->assertSame(2, $deleted);
        $this->assertSame(1, $this->rowCount($organizationId));
    }

    private function reserve(int $organizationId, string $key, string $request, string $now): bool
    {
        $at = self::at($now);

        return $this->repository->reserve(
            $organizationId,
            self::hash($key),
            self::hash($request),
            $at,
            $at->modify('+300 seconds'),
            $at->modify('+86400 seconds'),
        );
    }

    private function rowCount(int $organizationId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM idempotency_keys WHERE organization_id = :id');
        $statement->execute(['id' => $organizationId]);

        return (int) $statement->fetchColumn();
    }

    private static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }
}
