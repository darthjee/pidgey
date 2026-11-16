<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\UserRepositoryTestCase;

#[CoversClass(UserRepository::class)]
#[UsesClass(User::class)]
#[UsesClass(UserStatus::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(Sql::class)]
final class UserRepositorySessionTest extends UserRepositoryTestCase
{
    public function testFindApprovedBySessionFindsTheUserOfALiveSession(): void
    {
        $organizationId = $this->fixtures->organization();
        $userId = $this->fixtures->user(organizationId: $organizationId, username: 'gatekeeper', isAdmin: true);
        $this->fixtures->refreshToken(userId: $userId, sessionUuid: self::SESSION, expiresAt: $this->at('+1 hour'));

        $user = $this->repository->findApprovedBySession($userId, self::SESSION, $this->now());

        $this->assertNotNull($user);
        $this->assertEquals($this->repository->findById($userId), $user);
        $this->assertSame($organizationId, $user->organizationId);
        $this->assertSame('gatekeeper', $user->username);
        $this->assertTrue($user->isAdmin);
    }

    public function testFindApprovedBySessionFindsTheLiveRowOfARotatedSession(): void
    {
        $userId = $this->fixtures->user();
        $this->fixtures->refreshToken(
            userId: $userId,
            sessionUuid: self::SESSION,
            revokedAt: $this->at('-1 minute'),
            revokedReason: 'rotated',
        );
        $this->fixtures->refreshToken(userId: $userId, sessionUuid: self::SESSION);

        $this->assertSame($userId, $this->repository->findApprovedBySession($userId, self::SESSION, $this->now())?->id);
    }

    public function testFindApprovedBySessionIgnoresARevokedSession(): void
    {
        $userId = $this->fixtures->user();
        $this->fixtures->refreshToken(
            userId: $userId,
            sessionUuid: self::SESSION,
            revokedAt: $this->at('-1 minute'),
            revokedReason: 'logout',
        );

        $this->assertNull($this->repository->findApprovedBySession($userId, self::SESSION, $this->now()));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function expiredOffsets(): array
    {
        return [
            'expired' => ['-1 second'],
            'expiring exactly now' => ['+0 seconds'],
        ];
    }

    #[DataProvider('expiredOffsets')]
    public function testFindApprovedBySessionIgnoresAnExpiredSession(string $offset): void
    {
        $now = $this->now();
        $userId = $this->fixtures->user();
        $this->fixtures->refreshToken(
            userId: $userId,
            sessionUuid: self::SESSION,
            expiresAt: Sql::dateTime($now->modify($offset)),
        );

        $this->assertNull($this->repository->findApprovedBySession($userId, self::SESSION, $now));
    }

    public function testFindApprovedBySessionComparesAgainstTheGivenNow(): void
    {
        $userId = $this->fixtures->user();
        $this->fixtures->refreshToken(userId: $userId, sessionUuid: self::SESSION, expiresAt: $this->at('-1 hour'));
        $past = $this->now()->modify('-2 hours');

        $this->assertSame($userId, $this->repository->findApprovedBySession($userId, self::SESSION, $past)?->id);
    }

    public function testFindApprovedBySessionIgnoresAnUnknownSession(): void
    {
        $userId = $this->fixtures->user();
        $this->fixtures->refreshToken(userId: $userId, sessionUuid: self::SESSION);

        $this->assertNull($this->repository->findApprovedBySession($userId, self::OTHER_SESSION, $this->now()));
    }

    public function testFindApprovedBySessionIgnoresTheSessionOfAnotherUser(): void
    {
        $ownerId = $this->fixtures->user();
        $otherId = $this->fixtures->user();
        $this->fixtures->refreshToken(userId: $ownerId, sessionUuid: self::SESSION);
        $this->fixtures->refreshToken(userId: $otherId, sessionUuid: self::OTHER_SESSION);

        $this->assertNull($this->repository->findApprovedBySession($otherId, self::SESSION, $this->now()));
        $this->assertSame(
            $ownerId,
            $this->repository->findApprovedBySession($ownerId, self::SESSION, $this->now())?->id,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unapprovedStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'denied' => ['denied'],
        ];
    }

    #[DataProvider('unapprovedStatuses')]
    public function testFindApprovedBySessionIgnoresAUserThatIsNotApproved(string $status): void
    {
        $userId = $this->fixtures->user(status: $status);
        $this->fixtures->refreshToken(userId: $userId, sessionUuid: self::SESSION);

        $this->assertNull($this->repository->findApprovedBySession($userId, self::SESSION, $this->now()));
    }

    public function testFindApprovedBySessionIgnoresADeletedUser(): void
    {
        $userId = $this->fixtures->user();
        $this->fixtures->refreshToken(userId: $userId, sessionUuid: self::SESSION);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->assertNull($this->repository->findApprovedBySession($userId, self::SESSION, $this->now()));
    }
}
