<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Model;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use UnexpectedValueException;

#[CoversClass(User::class)]
#[UsesClass(Row::class)]
final class UserTest extends TestCase
{
    public function testFromRowMapsEveryColumn(): void
    {
        $user = User::fromRow($this->row());

        $this->assertSame(11, $user->id);
        $this->assertSame(7, $user->organizationId);
        $this->assertSame('alice', $user->username);
        $this->assertSame('alice@example.com', $user->email);
        $this->assertSame('$2y$10$digest', $user->passwordDigest);
        $this->assertFalse($user->isAdmin);
        $this->assertSame(UserStatus::Approved, $user->status);
        $this->assertSame('2026-01-02 03:04:05', $user->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $user->createdAt->getTimezone()->getName());
        $this->assertSame('2026-02-03 04:05:06', $user->updatedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $user->updatedAt->getTimezone()->getName());
    }

    public function testFromRowConvertsIsAdminToTrue(): void
    {
        $this->assertTrue(User::fromRow(['is_admin' => 1] + $this->row())->isAdmin);
    }

    /**
     * @return array<string, array{string, UserStatus}>
     */
    public static function statuses(): array
    {
        return [
            'pending' => ['pending', UserStatus::Pending],
            'approved' => ['approved', UserStatus::Approved],
            'denied' => ['denied', UserStatus::Denied],
        ];
    }

    #[DataProvider('statuses')]
    public function testFromRowConvertsStatus(string $value, UserStatus $expected): void
    {
        $this->assertSame($expected, User::fromRow(['status' => $value] + $this->row())->status);
    }

    public function testFromRowRejectsAnUnknownStatus(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("column 'status'");

        User::fromRow(['status' => 'banned'] + $this->row());
    }

    public function testFromRowRejectsAMissingColumn(): void
    {
        $row = $this->row();
        unset($row['password_digest']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(User::class . ": missing column 'password_digest'");

        User::fromRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => 11,
            'organization_id' => 7,
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password_digest' => '$2y$10$digest',
            'is_admin' => 0,
            'status' => 'approved',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-02-03 04:05:06',
        ];
    }
}
