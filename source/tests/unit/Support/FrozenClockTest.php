<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Pidgey\Tests\Support\FrozenClock;

#[CoversNothing]
final class FrozenClockTest extends TestCase
{
    public function testNowStartsAtTheGivenValue(): void
    {
        $this->assertSame(12.5, (new FrozenClock(12.5))->now());
    }

    public function testNowDefaultsToZero(): void
    {
        $this->assertSame(0.0, (new FrozenClock())->now());
    }

    public function testSleepAddsTheSeconds(): void
    {
        $clock = new FrozenClock(10.0);

        $clock->sleep(1.5);

        $this->assertSame(11.5, $clock->now());
    }

    public function testSleepZeroChangesNothing(): void
    {
        $clock = new FrozenClock(10.0);

        $clock->sleep(0);

        $this->assertSame(10.0, $clock->now());
    }

    public function testNegativeSleepThrows(): void
    {
        $clock = new FrozenClock(10.0);

        $this->expectException(InvalidArgumentException::class);

        $clock->sleep(-1);
    }
}
