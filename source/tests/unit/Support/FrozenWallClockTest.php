<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Pidgey\Tests\Support\FrozenWallClock;

#[CoversNothing]
final class FrozenWallClockTest extends TestCase
{
    public function testTakesAUnixTimestamp(): void
    {
        $now = (new FrozenWallClock(1_800_000_000))->now();

        $this->assertSame(1_800_000_000, $now->getTimestamp());
        $this->assertSame('UTC', $now->getTimezone()->getName());
    }

    public function testTakesADateTimeAndReturnsItInUtc(): void
    {
        $local = new DateTimeImmutable('2027-01-02 03:04:05', new DateTimeZone('America/Sao_Paulo'));

        $now = (new FrozenWallClock($local))->now();

        $this->assertSame('UTC', $now->getTimezone()->getName());
        $this->assertSame('2027-01-02 06:04:05', $now->format('Y-m-d H:i:s'));
    }

    public function testNowStandsStill(): void
    {
        $clock = new FrozenWallClock(1_800_000_000);

        $this->assertEquals($clock->now(), $clock->now());
    }

    public function testAdvanceMovesForward(): void
    {
        $clock = new FrozenWallClock(1_800_000_000);

        $clock->advance(60);

        $this->assertSame(1_800_000_060, $clock->now()->getTimestamp());
        $this->assertSame('UTC', $clock->now()->getTimezone()->getName());
    }

    public function testNegativeAdvanceThrows(): void
    {
        $clock = new FrozenWallClock(1_800_000_000);

        $this->expectException(InvalidArgumentException::class);

        $clock->advance(-1);
    }
}
