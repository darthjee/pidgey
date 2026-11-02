<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\UserPayload;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;

#[CoversClass(UserPayload::class)]
#[UsesClass(User::class)]
final class UserPayloadTest extends TestCase
{
    public function testHasExactlyTheSharedFields(): void
    {
        $this->assertSame(
            ['id' => 7, 'username' => 'bob', 'email' => 'bob@example.com', 'isAdmin' => false],
            UserPayload::fromUser($this->user(false, UserStatus::Pending)),
        );
    }

    public function testCarriesIsAdminAsABoolean(): void
    {
        $this->assertTrue(UserPayload::fromUser($this->user(true, UserStatus::Approved))['isAdmin']);
    }

    public function testNeverIncludesTheStatusTheDigestOrTheOrganization(): void
    {
        $payload = UserPayload::fromUser($this->user(false, UserStatus::Denied));

        foreach (['status', 'password', 'passwordDigest', 'password_digest', 'organizationId'] as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }

        $this->assertStringNotContainsString('$2y$', (string) json_encode($payload));
    }

    private function user(bool $isAdmin, UserStatus $status): User
    {
        $now = new DateTimeImmutable('2026-10-25T12:00:00Z');

        return new User(7, 3, 'bob', 'bob@example.com', '$2y$10$digest', $isAdmin, $status, $now, $now);
    }
}
