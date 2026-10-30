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
use Pidgey\Model\PasswordResetToken;
use Pidgey\Model\Row;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(PasswordResetTokenRepository::class)]
#[UsesClass(PasswordResetToken::class)]
#[UsesClass(Row::class)]
#[UsesClass(Sql::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
final class PasswordResetTokenRepositoryTest extends RepositoryTestCase
{
    private PasswordResetTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PasswordResetTokenRepository($this->pdo);
    }

    public function testCreateReturnsEveryField(): void
    {
        $userId = $this->fixtures->user();
        $tokenHash = str_repeat('a', 64);

        $token = $this->repository->create(
            $tokenHash,
            $userId,
            $this->utc('2020-05-06 07:08:09'),
            $this->utc('2020-05-06 08:08:09'),
        );

        $this->assertGreaterThan(0, $token->id);
        $this->assertSame($tokenHash, $token->tokenHash);
        $this->assertSame($userId, $token->userId);
        $this->assertSame('2020-05-06 07:08:09', $token->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->createdAt->getTimezone()->getName());
        $this->assertSame('2020-05-06 08:08:09', $token->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->expiresAt->getTimezone()->getName());
        $this->assertNull($token->usedAt);
        $this->assertEquals($token, $this->repository->findById($token->id));
    }

    public function testCreateStoresCreatedAtAsGivenNotTheDatabaseClock(): void
    {
        $createdAt = $this->utc('2001-02-03 04:05:06');

        $token = $this->repository->create(
            str_repeat('b', 64),
            $this->fixtures->user(),
            $createdAt,
            $this->utc('2001-02-03 05:05:06'),
        );

        $this->assertEquals($createdAt, $token->createdAt);
    }

    public function testCreateRejectsAnUnknownUser(): void
    {
        $userId = $this->fixtures->user();

        $this->expectException(PDOException::class);

        $this->createToken(str_repeat('c', 64), $userId + 1000000);
    }

    public function testCreateRejectsADuplicateTokenHash(): void
    {
        $tokenHash = str_repeat('d', 64);
        $this->fixtures->passwordResetToken(tokenHash: $tokenHash);

        $this->expectException(PDOException::class);

        $this->createToken($tokenHash, $this->fixtures->user());
    }

    public function testFindByIdFindsAFixtureRow(): void
    {
        $userId = $this->fixtures->user();
        $id = $this->fixtures->passwordResetToken(userId: $userId);

        $this->assertSame($userId, $this->repository->findById($id)?->userId);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $id = $this->fixtures->passwordResetToken();

        $this->assertNull($this->repository->findById($id + 1000000));
    }

    public function testFindByTokenHashFindsAFixtureRow(): void
    {
        $tokenHash = str_repeat('e', 64);
        $id = $this->fixtures->passwordResetToken(tokenHash: $tokenHash);

        $this->assertSame($id, $this->repository->findByTokenHash($tokenHash)?->id);
    }

    public function testFindByTokenHashReturnsNullWhenNotFound(): void
    {
        $this->fixtures->passwordResetToken(tokenHash: str_repeat('e', 64));

        $this->assertNull($this->repository->findByTokenHash(str_repeat('f', 64)));
    }

    public function testFindByIdReadsUsedAt(): void
    {
        $id = $this->fixtures->passwordResetToken(usedAt: '2026-03-02 10:00:00');

        $this->assertSame(
            '2026-03-02 10:00:00',
            $this->repository->findById($id)?->usedAt?->format('Y-m-d H:i:s'),
        );
    }

    private function createToken(string $tokenHash, int $userId): PasswordResetToken
    {
        return $this->repository->create(
            $tokenHash,
            $userId,
            $this->utc('2026-03-01 10:00:00'),
            $this->utc('2026-03-01 11:00:00'),
        );
    }

    private function utc(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('UTC'));
    }
}
