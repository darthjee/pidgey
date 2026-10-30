<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Model;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Model\RefreshToken;
use Pidgey\Model\RevokedReason;
use Pidgey\Model\Row;
use UnexpectedValueException;

#[CoversClass(RefreshToken::class)]
#[UsesClass(Row::class)]
final class RefreshTokenTest extends TestCase
{
    private const TOKEN_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testFromRowMapsEveryColumn(): void
    {
        $token = RefreshToken::fromRow($this->row());

        $this->assertSame(21, $token->id);
        $this->assertSame(self::TOKEN_HASH, $token->tokenHash);
        $this->assertSame(11, $token->userId);
        $this->assertSame('2026-01-02 03:04:05', $token->issuedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->issuedAt->getTimezone()->getName());
        $this->assertSame('2026-01-09 03:04:05', $token->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->expiresAt->getTimezone()->getName());
        $this->assertNull($token->revokedAt);
        $this->assertNull($token->revokedReason);
        $this->assertSame('6f1c2a4e-8b3d-4c5f-9a7e-0d1b2c3e4f5a', $token->sessionUuid);
        $this->assertSame('2026-01-01 00:00:00', $token->startedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->startedAt->getTimezone()->getName());
    }

    public function testFromRowMapsARevokedToken(): void
    {
        $token = RefreshToken::fromRow(
            ['revoked_at' => '2026-01-03 04:05:06', 'revoked_reason' => 'logout'] + $this->row(),
        );

        $this->assertNotNull($token->revokedAt);
        $this->assertSame('2026-01-03 04:05:06', $token->revokedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->revokedAt->getTimezone()->getName());
        $this->assertSame(RevokedReason::Logout, $token->revokedReason);
    }

    /**
     * @return array<string, array{string, RevokedReason}>
     */
    public static function reasons(): array
    {
        return [
            'rotated' => ['rotated', RevokedReason::Rotated],
            'logout' => ['logout', RevokedReason::Logout],
            'password_reset' => ['password_reset', RevokedReason::PasswordReset],
            'replay_detected' => ['replay_detected', RevokedReason::ReplayDetected],
            'admin_denied' => ['admin_denied', RevokedReason::AdminDenied],
            'admin_edit' => ['admin_edit', RevokedReason::AdminEdit],
        ];
    }

    #[DataProvider('reasons')]
    public function testFromRowConvertsRevokedReason(string $value, RevokedReason $expected): void
    {
        $row = ['revoked_at' => '2026-01-03 04:05:06', 'revoked_reason' => $value] + $this->row();

        $this->assertSame($expected, RefreshToken::fromRow($row)->revokedReason);
    }

    public function testFromRowRejectsAnUnknownRevokedReason(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("column 'revoked_reason'");

        RefreshToken::fromRow(['revoked_reason' => 'stolen'] + $this->row());
    }

    public function testFromRowRejectsAMissingColumn(): void
    {
        $row = $this->row();
        unset($row['session_uuid']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(RefreshToken::class . ": missing column 'session_uuid'");

        RefreshToken::fromRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => 21,
            'token_hash' => self::TOKEN_HASH,
            'user_id' => 11,
            'issued_at' => '2026-01-02 03:04:05',
            'expires_at' => '2026-01-09 03:04:05',
            'revoked_at' => null,
            'revoked_reason' => null,
            'session_uuid' => '6f1c2a4e-8b3d-4c5f-9a7e-0d1b2c3e4f5a',
            'started_at' => '2026-01-01 00:00:00',
        ];
    }
}
