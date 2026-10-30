<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\RefreshToken;
use Pidgey\Model\RevokedReason;
use Pidgey\Model\Row;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(RefreshTokenRepository::class)]
#[UsesClass(RefreshToken::class)]
#[UsesClass(RevokedReason::class)]
#[UsesClass(Row::class)]
#[UsesClass(Sql::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
final class RefreshTokenRepositoryTest extends RepositoryTestCase
{
    private const SESSION_UUID = '6f1c2a4e-8b3d-4c5f-9a7e-0d1b2c3e4f5a';

    private RefreshTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new RefreshTokenRepository($this->pdo);
    }

    public function testCreateReturnsEveryField(): void
    {
        $userId = $this->fixtures->user();
        $tokenHash = str_repeat('a', 64);

        $token = $this->repository->create(
            $tokenHash,
            $userId,
            self::SESSION_UUID,
            $this->utc('2026-03-01 10:00:00'),
            $this->utc('2026-03-08 10:00:00'),
            $this->utc('2026-02-28 09:00:00'),
        );

        $this->assertGreaterThan(0, $token->id);
        $this->assertSame($tokenHash, $token->tokenHash);
        $this->assertSame($userId, $token->userId);
        $this->assertSame(self::SESSION_UUID, $token->sessionUuid);
        $this->assertSame('2026-03-01 10:00:00', $token->issuedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->issuedAt->getTimezone()->getName());
        $this->assertSame('2026-03-08 10:00:00', $token->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->expiresAt->getTimezone()->getName());
        $this->assertSame('2026-02-28 09:00:00', $token->startedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->startedAt->getTimezone()->getName());
        $this->assertNull($token->revokedAt);
        $this->assertNull($token->revokedReason);
        $this->assertEquals($token, $this->repository->findById($token->id));
    }

    public function testCreateStoresNonUtcTimesAsTheirUtcInstant(): void
    {
        $saoPaulo = new DateTimeZone('America/Sao_Paulo');

        $token = $this->repository->create(
            str_repeat('b', 64),
            $this->fixtures->user(),
            self::SESSION_UUID,
            new DateTimeImmutable('2026-03-01 07:00:00', $saoPaulo),
            new DateTimeImmutable('2026-03-08 07:00:00', $saoPaulo),
            new DateTimeImmutable('2026-03-01 06:30:00', $saoPaulo),
        );

        $this->assertSame('2026-03-01 10:00:00', $token->issuedAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-08 10:00:00', $token->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-01 09:30:00', $token->startedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->issuedAt->getTimezone()->getName());
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
        $this->fixtures->refreshToken(tokenHash: $tokenHash);

        $this->expectException(PDOException::class);

        $this->createToken($tokenHash, $this->fixtures->user());
    }

    public function testFindByIdFindsAFixtureRow(): void
    {
        $userId = $this->fixtures->user();
        $id = $this->fixtures->refreshToken(userId: $userId);

        $this->assertSame($userId, $this->repository->findById($id)?->userId);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $id = $this->fixtures->refreshToken();

        $this->assertNull($this->repository->findById($id + 1000000));
    }

    public function testFindByTokenHashFindsAFixtureRow(): void
    {
        $tokenHash = str_repeat('e', 64);
        $id = $this->fixtures->refreshToken(tokenHash: $tokenHash);

        $this->assertSame($id, $this->repository->findByTokenHash($tokenHash)?->id);
    }

    public function testFindByTokenHashReturnsNullWhenNotFound(): void
    {
        $this->fixtures->refreshToken(tokenHash: str_repeat('e', 64));

        $this->assertNull($this->repository->findByTokenHash(str_repeat('f', 64)));
    }

    /**
     * @return array<string, array{RevokedReason}>
     */
    public static function reasons(): array
    {
        $reasons = [];

        foreach (RevokedReason::cases() as $reason) {
            $reasons[$reason->value] = [$reason];
        }

        return $reasons;
    }

    #[DataProvider('reasons')]
    public function testFindByIdReadsTheRevokedReason(RevokedReason $reason): void
    {
        $id = $this->fixtures->refreshToken(revokedAt: '2026-03-02 10:00:00', revokedReason: $reason->value);

        $token = $this->repository->findById($id);

        $this->assertSame($reason, $token?->revokedReason);
        $this->assertSame('2026-03-02 10:00:00', $token?->revokedAt?->format('Y-m-d H:i:s'));
    }

    private function createToken(string $tokenHash, int $userId): RefreshToken
    {
        return $this->repository->create(
            $tokenHash,
            $userId,
            self::SESSION_UUID,
            $this->utc('2026-03-01 10:00:00'),
            $this->utc('2026-03-08 10:00:00'),
            $this->utc('2026-03-01 10:00:00'),
        );
    }

    private function utc(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('UTC'));
    }
}
