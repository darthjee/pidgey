<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Model;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Model\IdempotencyKey;
use Pidgey\Model\IdempotencyState;
use Pidgey\Model\Row;
use UnexpectedValueException;

#[CoversClass(IdempotencyKey::class)]
#[UsesClass(Row::class)]
final class IdempotencyKeyTest extends TestCase
{
    public function testFromRowMapsEveryColumnOfACompletedKey(): void
    {
        $key = IdempotencyKey::fromRow($this->row());

        $this->assertSame(IdempotencyState::Completed, $key->state);
        $this->assertSame(str_repeat('a', 64), $key->requestHash);
        $this->assertSame(200, $key->responseStatus);
        $this->assertSame('{"ok":true}', $key->responseBody);
        $this->assertSame('2026-01-02 03:09:05', $key->leaseUntil->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $key->leaseUntil->getTimezone()->getName());
        $this->assertSame('2026-01-03 03:04:05', $key->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $key->expiresAt->getTimezone()->getName());
    }

    public function testFromRowMapsAnInFlightKeyWithoutAResponse(): void
    {
        $key = IdempotencyKey::fromRow(
            ['state' => 'in_flight', 'response_status' => null, 'response_body' => null] + $this->row(),
        );

        $this->assertSame(IdempotencyState::InFlight, $key->state);
        $this->assertNull($key->responseStatus);
        $this->assertNull($key->responseBody);
    }

    public function testFromRowAcceptsAStringStatus(): void
    {
        $key = IdempotencyKey::fromRow(['response_status' => '504'] + $this->row());

        $this->assertSame(504, $key->responseStatus);
    }

    public function testFromRowRejectsAnUnknownState(): void
    {
        $this->expectException(UnexpectedValueException::class);

        IdempotencyKey::fromRow(['state' => 'unknown'] + $this->row());
    }

    public function testFromRowRequiresEveryColumn(): void
    {
        $row = $this->row();
        unset($row['request_hash']);

        $this->expectException(LogicException::class);

        IdempotencyKey::fromRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'state' => 'completed',
            'request_hash' => str_repeat('a', 64),
            'response_status' => 200,
            'response_body' => '{"ok":true}',
            'lease_until' => '2026-01-02 03:09:05',
            'expires_at' => '2026-01-03 03:04:05',
        ];
    }
}
