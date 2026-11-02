<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Auth\Caller;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;

#[CoversClass(AuthenticatedUser::class)]
#[UsesClass(User::class)]
final class AuthenticatedUserTest extends TestCase
{
    private const SESSION = '0b8f2c3e-1d4a-4b6c-9e7f-a1b2c3d4e5f6';

    public function testIsACaller(): void
    {
        $user = new AuthenticatedUser(7, self::SESSION, 'bob', false, 3);

        $this->assertInstanceOf(Caller::class, $user);
        $this->assertSame(3, $user->organizationId());
    }

    public function testExposesItsFields(): void
    {
        $user = new AuthenticatedUser(7, self::SESSION, 'bob', true, 3);

        $this->assertSame(7, $user->id);
        $this->assertSame(self::SESSION, $user->sessionId);
        $this->assertSame('bob', $user->username);
        $this->assertTrue($user->isAdmin);
        $this->assertSame(3, $user->organizationId);
    }

    public function testIsBuiltFromTheDatabaseUserAndTheSessionId(): void
    {
        $now = new DateTimeImmutable('2026-10-25T12:00:00Z');
        $row = new User(7, 3, 'bob', 'bob@example.com', 'digest', true, UserStatus::Approved, $now, $now);

        $this->assertEquals(
            new AuthenticatedUser(7, self::SESSION, 'bob', true, 3),
            AuthenticatedUser::fromUser($row, self::SESSION),
        );
    }
}
