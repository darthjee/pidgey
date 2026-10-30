<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Model;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Model\AuthorizationRequest;
use Pidgey\Model\AuthorizationRequestStatus;
use Pidgey\Model\Row;
use UnexpectedValueException;

#[CoversClass(AuthorizationRequest::class)]
#[UsesClass(Row::class)]
final class AuthorizationRequestTest extends TestCase
{
    private const POLL_TOKEN_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testFromRowMapsEveryColumn(): void
    {
        $request = AuthorizationRequest::fromRow($this->row());

        $this->assertSame(41, $request->id);
        $this->assertSame('0b7e9c1d-2f3a-4b5c-8d6e-7f8091a2b3c4', $request->uuid);
        $this->assertSame('alice', $request->username);
        $this->assertNull($request->userId);
        $this->assertSame(AuthorizationRequestStatus::Open, $request->status);
        $this->assertSame(self::POLL_TOKEN_HASH, $request->pollTokenHash);
        $this->assertSame('203.0.113.7', $request->requestIp);
        $this->assertSame('Mozilla/5.0', $request->requestUserAgent);
        $this->assertNull($request->approvedByUserId);
        $this->assertSame('2026-01-02 03:04:05', $request->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $request->createdAt->getTimezone()->getName());
        $this->assertSame('2026-01-02 03:14:05', $request->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $request->expiresAt->getTimezone()->getName());
        $this->assertNull($request->resolvedAt);
        $this->assertNull($request->loggedAt);
        $this->assertSame(0, $request->authorizeFailedAttempts);
        $this->assertNull($request->authorizeLockedUntil);
    }

    public function testFromRowMapsTheNullableColumnsWhenSet(): void
    {
        $request = AuthorizationRequest::fromRow(
            [
                'user_id' => 11,
                'status' => 'logged',
                'approved_by_user_id' => 12,
                'resolved_at' => '2026-01-02 03:05:00',
                'logged_at' => '2026-01-02 03:06:00',
                'authorize_failed_attempts' => 3,
                'authorize_locked_until' => '2026-01-02 03:20:00',
            ] + $this->row(),
        );

        $this->assertSame(11, $request->userId);
        $this->assertSame(12, $request->approvedByUserId);
        $this->assertNotNull($request->resolvedAt);
        $this->assertSame('2026-01-02 03:05:00', $request->resolvedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $request->resolvedAt->getTimezone()->getName());
        $this->assertNotNull($request->loggedAt);
        $this->assertSame('2026-01-02 03:06:00', $request->loggedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $request->loggedAt->getTimezone()->getName());
        $this->assertSame(3, $request->authorizeFailedAttempts);
        $this->assertNotNull($request->authorizeLockedUntil);
        $this->assertSame('2026-01-02 03:20:00', $request->authorizeLockedUntil->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $request->authorizeLockedUntil->getTimezone()->getName());
    }

    /**
     * @return array<string, array{string, AuthorizationRequestStatus}>
     */
    public static function statuses(): array
    {
        return [
            'open' => ['open', AuthorizationRequestStatus::Open],
            'approved' => ['approved', AuthorizationRequestStatus::Approved],
            'denied' => ['denied', AuthorizationRequestStatus::Denied],
            'logged' => ['logged', AuthorizationRequestStatus::Logged],
            'expired' => ['expired', AuthorizationRequestStatus::Expired],
        ];
    }

    #[DataProvider('statuses')]
    public function testFromRowConvertsStatus(string $value, AuthorizationRequestStatus $expected): void
    {
        $this->assertSame($expected, AuthorizationRequest::fromRow(['status' => $value] + $this->row())->status);
    }

    public function testFromRowRejectsAnUnknownStatus(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("column 'status'");

        AuthorizationRequest::fromRow(['status' => 'pending'] + $this->row());
    }

    public function testFromRowRejectsAMissingColumn(): void
    {
        $row = $this->row();
        unset($row['poll_token_hash']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(AuthorizationRequest::class . ": missing column 'poll_token_hash'");

        AuthorizationRequest::fromRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => 41,
            'uuid' => '0b7e9c1d-2f3a-4b5c-8d6e-7f8091a2b3c4',
            'username' => 'alice',
            'user_id' => null,
            'status' => 'open',
            'poll_token_hash' => self::POLL_TOKEN_HASH,
            'request_ip' => '203.0.113.7',
            'request_user_agent' => 'Mozilla/5.0',
            'approved_by_user_id' => null,
            'created_at' => '2026-01-02 03:04:05',
            'expires_at' => '2026-01-02 03:14:05',
            'resolved_at' => null,
            'logged_at' => null,
            'authorize_failed_attempts' => 0,
            'authorize_locked_until' => null,
        ];
    }
}
