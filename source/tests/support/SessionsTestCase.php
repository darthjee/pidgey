<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PDO;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Infra\Transaction;
use Pidgey\Model\RefreshToken;
use Pidgey\Model\User;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\UserRepository;

/**
 * Base class for the session service tests.
 *
 * Runs without an outer test transaction, because the service opens its own; every table is
 * emptied before and after each test instead.
 */
abstract class SessionsTestCase extends IntegrationTestCase
{
    protected const KEY = 'sessions-test-secret-key-0123456789abcdef';

    protected const NOW = 1_800_000_000;

    protected const TTL = 604800;

    protected const ISSUED = self::NOW - 3600;

    protected const STARTED = self::NOW - 86400;

    protected PDO $pdo;

    protected Fixtures $fixtures;

    protected AccessToken $accessToken;

    protected Sessions $sessions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = self::pdo();
        DatabaseCleaner::truncateAll($this->pdo);
        $this->fixtures = new Fixtures($this->pdo);
        $clock = new FrozenWallClock(self::NOW);
        $this->accessToken = new AccessToken(new SecretKeys(self::KEY), 900, $clock);
        $refreshTokens = new RefreshTokenRepository($this->pdo);
        $transaction = new Transaction($this->pdo);
        $this->sessions = new Sessions(
            $refreshTokens,
            new UserRepository($this->pdo),
            new SessionRotation($refreshTokens, $transaction, $this->accessToken, self::TTL),
            $this->accessToken,
            $clock,
            self::TTL,
        );
    }

    protected function tearDown(): void
    {
        DatabaseCleaner::truncateAll($this->pdo);

        parent::tearDown();
    }

    protected static function at(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    protected function entity(int $id): RefreshToken
    {
        return (new RefreshTokenRepository($this->pdo))->findById($id) ?? self::fail('The fixture token is missing.');
    }

    protected function deleteUser(int $id): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    protected function user(string $status = 'approved'): User
    {
        $id = $this->fixtures->user(status: $status);

        return (new UserRepository($this->pdo))->findById($id) ?? self::fail('The fixture user is missing.');
    }

    protected function token(
        int $userId,
        string $plain,
        ?string $sessionUuid = null,
        ?int $expiresAt = null,
        ?string $revokedReason = null,
    ): int {
        return $this->fixtures->refreshToken(
            userId: $userId,
            tokenHash: hash('sha256', $plain),
            sessionUuid: $sessionUuid,
            issuedAt: self::at(self::ISSUED),
            expiresAt: self::at($expiresAt ?? self::NOW + 86400),
            revokedAt: $revokedReason === null ? null : self::at(self::ISSUED + 60),
            revokedReason: $revokedReason,
            startedAt: self::at(self::STARTED),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rows(): array
    {
        $rows = [];

        $statement = $this->pdo->query('SELECT * FROM refresh_tokens ORDER BY id');

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(int) $row['id']] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rowByHash(string $tokenHash): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM refresh_tokens WHERE token_hash = :hash');
        $statement->execute(['hash' => $tokenHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    protected function onlyRow(): array
    {
        $rows = $this->pdo->query('SELECT * FROM refresh_tokens')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $rows);

        return $rows[0];
    }

    protected function rowCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM refresh_tokens')->fetchColumn();
    }
}
