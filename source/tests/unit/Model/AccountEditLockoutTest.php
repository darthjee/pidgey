<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Model;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Model\AccountEditLockout;
use Pidgey\Model\Row;

#[CoversClass(AccountEditLockout::class)]
#[UsesClass(Row::class)]
final class AccountEditLockoutTest extends TestCase
{
    public function testFromRowMapsEveryColumn(): void
    {
        $lockout = AccountEditLockout::fromRow($this->row());

        $this->assertSame(51, $lockout->id);
        $this->assertSame(11, $lockout->userId);
        $this->assertSame(0, $lockout->failedAttempts);
        $this->assertNull($lockout->lockedUntil);
        $this->assertSame('2026-01-02 03:04:05', $lockout->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $lockout->createdAt->getTimezone()->getName());
        $this->assertSame('2026-02-03 04:05:06', $lockout->updatedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $lockout->updatedAt->getTimezone()->getName());
    }

    public function testFromRowMapsALockedRow(): void
    {
        $lockout = AccountEditLockout::fromRow(
            ['failed_attempts' => 5, 'locked_until' => '2026-02-03 04:20:06'] + $this->row(),
        );

        $this->assertSame(5, $lockout->failedAttempts);
        $this->assertNotNull($lockout->lockedUntil);
        $this->assertSame('2026-02-03 04:20:06', $lockout->lockedUntil->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $lockout->lockedUntil->getTimezone()->getName());
    }

    public function testFromRowRejectsAMissingColumn(): void
    {
        $row = $this->row();
        unset($row['failed_attempts']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(AccountEditLockout::class . ": missing column 'failed_attempts'");

        AccountEditLockout::fromRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => 51,
            'user_id' => 11,
            'failed_attempts' => 0,
            'locked_until' => null,
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-02-03 04:05:06',
        ];
    }
}
