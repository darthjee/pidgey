<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Model;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Model\PasswordResetToken;
use Pidgey\Model\Row;

#[CoversClass(PasswordResetToken::class)]
#[UsesClass(Row::class)]
final class PasswordResetTokenTest extends TestCase
{
    private const TOKEN_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testFromRowMapsEveryColumn(): void
    {
        $token = PasswordResetToken::fromRow($this->row());

        $this->assertSame(31, $token->id);
        $this->assertSame(self::TOKEN_HASH, $token->tokenHash);
        $this->assertSame(11, $token->userId);
        $this->assertSame('2026-01-02 03:04:05', $token->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->createdAt->getTimezone()->getName());
        $this->assertSame('2026-01-02 04:04:05', $token->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->expiresAt->getTimezone()->getName());
        $this->assertNull($token->usedAt);
    }

    public function testFromRowMapsAUsedToken(): void
    {
        $token = PasswordResetToken::fromRow(['used_at' => '2026-01-02 03:30:00'] + $this->row());

        $this->assertNotNull($token->usedAt);
        $this->assertSame('2026-01-02 03:30:00', $token->usedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->usedAt->getTimezone()->getName());
    }

    public function testFromRowRejectsAMissingColumn(): void
    {
        $row = $this->row();
        unset($row['used_at']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PasswordResetToken::class . ": missing column 'used_at'");

        PasswordResetToken::fromRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => 31,
            'token_hash' => self::TOKEN_HASH,
            'user_id' => 11,
            'created_at' => '2026-01-02 03:04:05',
            'expires_at' => '2026-01-02 04:04:05',
            'used_at' => null,
        ];
    }
}
