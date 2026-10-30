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
use Pidgey\Model\AuthorizationRequest;
use Pidgey\Model\AuthorizationRequestStatus;
use Pidgey\Model\Row;
use Pidgey\Repository\AuthorizationRequestRepository;
use Pidgey\Repository\Sql;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(AuthorizationRequestRepository::class)]
#[UsesClass(AuthorizationRequest::class)]
#[UsesClass(AuthorizationRequestStatus::class)]
#[UsesClass(Row::class)]
#[UsesClass(Sql::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
final class AuthorizationRequestRepositoryTest extends RepositoryTestCase
{
    private const UUID = '0b7e9c1d-2f3a-4b5c-8d6e-7f8091a2b3c4';

    private const OTHER_UUID = '1c8f0d2e-3a4b-4c6d-9e7f-8091a2b3c4d5';

    private AuthorizationRequestRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new AuthorizationRequestRepository($this->pdo);
    }

    public function testCreateReturnsEveryField(): void
    {
        $userId = $this->fixtures->user();
        $pollTokenHash = str_repeat('a', 64);

        $request = $this->repository->create(
            self::UUID,
            'alice',
            $userId,
            $pollTokenHash,
            '2001:db8::1',
            'Mozilla/5.0',
            $this->utc('2026-03-01 10:00:00'),
            $this->utc('2026-03-01 10:10:00'),
        );

        $this->assertGreaterThan(0, $request->id);
        $this->assertSame(self::UUID, $request->uuid);
        $this->assertSame('alice', $request->username);
        $this->assertSame($userId, $request->userId);
        $this->assertSame(AuthorizationRequestStatus::Open, $request->status);
        $this->assertSame($pollTokenHash, $request->pollTokenHash);
        $this->assertSame('2001:db8::1', $request->requestIp);
        $this->assertSame('Mozilla/5.0', $request->requestUserAgent);
        $this->assertNull($request->approvedByUserId);
        $this->assertSame('2026-03-01 10:00:00', $request->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $request->createdAt->getTimezone()->getName());
        $this->assertSame('2026-03-01 10:10:00', $request->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $request->expiresAt->getTimezone()->getName());
        $this->assertNull($request->resolvedAt);
        $this->assertNull($request->loggedAt);
        $this->assertSame(0, $request->authorizeFailedAttempts);
        $this->assertNull($request->authorizeLockedUntil);
        $this->assertEquals($request, $this->repository->findById($request->id));
    }

    public function testCreateAcceptsAnUnknownUsername(): void
    {
        $request = $this->createRequest(self::UUID, str_repeat('b', 64), null);

        $this->assertNull($request->userId);
        $this->assertNull($this->repository->findById($request->id)?->userId);
    }

    public function testCreateRejectsAnUnknownUser(): void
    {
        $userId = $this->fixtures->user();

        $this->expectException(PDOException::class);

        $this->createRequest(self::UUID, str_repeat('c', 64), $userId + 1000000);
    }

    public function testCreateRejectsADuplicateUuid(): void
    {
        $this->fixtures->authorizationRequest(uuid: self::UUID);

        $this->expectException(PDOException::class);

        $this->createRequest(self::UUID, str_repeat('d', 64), null);
    }

    public function testCreateRejectsADuplicatePollTokenHash(): void
    {
        $pollTokenHash = str_repeat('e', 64);
        $this->fixtures->authorizationRequest(pollTokenHash: $pollTokenHash);

        $this->expectException(PDOException::class);

        $this->createRequest(self::OTHER_UUID, $pollTokenHash, null);
    }

    public function testAnUnknownApproverIsRejected(): void
    {
        $userId = $this->fixtures->user();

        $this->expectException(PDOException::class);

        $this->fixtures->authorizationRequest(status: 'approved', approvedByUserId: $userId + 1000000);
    }

    public function testFindByIdReadsAnApprovedRequest(): void
    {
        $approverId = $this->fixtures->user();
        $id = $this->fixtures->authorizationRequest(status: 'approved', approvedByUserId: $approverId);

        $request = $this->repository->findById($id);

        $this->assertSame(AuthorizationRequestStatus::Approved, $request?->status);
        $this->assertSame($approverId, $request?->approvedByUserId);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $id = $this->fixtures->authorizationRequest();

        $this->assertNull($this->repository->findById($id + 1000000));
    }

    public function testFindByUuidFindsAFixtureRow(): void
    {
        $id = $this->fixtures->authorizationRequest(uuid: self::UUID);

        $this->assertSame($id, $this->repository->findByUuid(self::UUID)?->id);
    }

    public function testFindByUuidReturnsNullWhenNotFound(): void
    {
        $this->fixtures->authorizationRequest(uuid: self::UUID);

        $this->assertNull($this->repository->findByUuid(self::OTHER_UUID));
    }

    public function testFindByPollTokenHashFindsAFixtureRow(): void
    {
        $pollTokenHash = str_repeat('f', 64);
        $id = $this->fixtures->authorizationRequest(userId: null, pollTokenHash: $pollTokenHash);

        $request = $this->repository->findByPollTokenHash($pollTokenHash);

        $this->assertSame($id, $request?->id);
        $this->assertNull($request?->userId);
    }

    public function testFindByPollTokenHashReturnsNullWhenNotFound(): void
    {
        $this->fixtures->authorizationRequest(pollTokenHash: str_repeat('f', 64));

        $this->assertNull($this->repository->findByPollTokenHash(str_repeat('0', 64)));
    }

    private function createRequest(string $uuid, string $pollTokenHash, ?int $userId): AuthorizationRequest
    {
        return $this->repository->create(
            $uuid,
            'bob',
            $userId,
            $pollTokenHash,
            '203.0.113.7',
            'PHPUnit',
            $this->utc('2026-03-01 10:00:00'),
            $this->utc('2026-03-01 10:10:00'),
        );
    }

    private function utc(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('UTC'));
    }
}
