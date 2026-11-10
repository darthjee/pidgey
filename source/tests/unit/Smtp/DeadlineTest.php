<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\Deadline;
use Pidgey\Tests\Support\FrozenClock;

#[CoversClass(Deadline::class)]
final class DeadlineTest extends TestCase
{
    public function testRemainingCountsDown(): void
    {
        $clock = new FrozenClock(100.0);
        $deadline = Deadline::start($clock, 10.0);

        $this->assertSame(10.0, $deadline->remaining());
        $this->assertFalse($deadline->expired());

        $clock->sleep(2.5);

        $this->assertSame(7.5, $deadline->remaining());
    }

    public function testRemainingIsClampedAtZero(): void
    {
        $clock = new FrozenClock();
        $deadline = Deadline::start($clock, 1.0);

        $clock->sleep(5.0);

        $this->assertSame(0.0, $deadline->remaining());
        $this->assertTrue($deadline->expired());
    }

    public function testExpiresExactlyAtTheEnd(): void
    {
        $clock = new FrozenClock();
        $deadline = Deadline::start($clock, 1.0);

        $clock->sleep(1.0);

        $this->assertTrue($deadline->expired());
    }

    public function testZeroDeadlineIsExpiredAtOnce(): void
    {
        $this->assertTrue(Deadline::start(new FrozenClock(), 0.0)->expired());
    }

    public function testWithinShorterThanTheParentUsesItsOwnTime(): void
    {
        $clock = new FrozenClock();
        $parent = Deadline::start($clock, 25.0);

        $child = $parent->within(10.0);

        $this->assertSame(10.0, $child->remaining());
        $clock->sleep(10.0);
        $this->assertTrue($child->expired());
        $this->assertSame(15.0, $parent->remaining());
    }

    public function testWithinLongerThanTheParentStopsAtTheParent(): void
    {
        $clock = new FrozenClock();
        $parent = Deadline::start($clock, 25.0);
        $clock->sleep(20.0);

        $child = $parent->within(10.0);

        $this->assertSame(5.0, $child->remaining());
        $clock->sleep(5.0);
        $this->assertTrue($child->expired());
    }

    public function testWithinStartsFromNow(): void
    {
        $clock = new FrozenClock();
        $parent = Deadline::start($clock, 25.0);
        $clock->sleep(3.0);

        $this->assertSame(10.0, $parent->within(10.0)->remaining());
    }
}
