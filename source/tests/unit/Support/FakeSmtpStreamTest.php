<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Support;

use Closure;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\Deadline;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\StreamFailure;
use Pidgey\Smtp\StreamFailureKind;
use Pidgey\Tests\Support\FakeSmtpStream;
use Pidgey\Tests\Support\FrozenClock;

#[CoversNothing]
final class FakeSmtpStreamTest extends TestCase
{
    private FrozenClock $clock;

    private FakeSmtpStream $stream;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->stream = new FakeSmtpStream($this->clock);
    }

    public function testScriptedSessionRunsInOrder(): void
    {
        $this->stream
            ->expectOpen()
            ->reply("220 ready\r\n")
            ->expectLine('EHLO [192.0.2.10]')
            ->reply("250-mail.example.com\r\n250 STARTTLS\r\n")
            ->expectLine('STARTTLS')
            ->reply("220 go\r\n")
            ->expectUpgrade();

        $this->stream->open('192.0.2.1', 587, 'mail.example.com', false, null, $this->deadline());
        $this->assertSame("220 ready\r\n", $this->stream->readLine($this->deadline()));
        $this->stream->writeLine('EHLO [192.0.2.10]', $this->deadline());
        $this->assertSame("250-mail.example.com\r\n", $this->stream->readLine($this->deadline()));
        $this->assertSame("250 STARTTLS\r\n", $this->stream->readLine($this->deadline()));
        $this->stream->writeLine('STARTTLS', $this->deadline());
        $this->assertSame("220 go\r\n", $this->stream->readLine($this->deadline()));
        $this->stream->upgradeToTls('mail.example.com', '/ca.pem', $this->deadline());
        $this->stream->assertDone();

        $this->assertSame(
            [[
                'address' => '192.0.2.1',
                'port' => 587,
                'peerName' => 'mail.example.com',
                'implicitTls' => false,
                'caFile' => null,
            ]],
            $this->stream->opens(),
        );
        $this->assertSame([['peerName' => 'mail.example.com', 'caFile' => '/ca.pem']], $this->stream->upgrades());
    }

    public function testLocalAddressIsConfigurable(): void
    {
        $this->assertSame('192.0.2.10', $this->stream->localAddress());
        $this->assertSame('2001:db8::7', (new FakeSmtpStream($this->clock, '2001:db8::7'))->localAddress());
    }

    public function testMismatchedLineFailsTheTest(): void
    {
        $this->stream->expectLine('QUIT');

        $this->expectException(AssertionFailedError::class);

        $this->stream->writeLine('RSET', $this->deadline());
    }

    public function testLinePrefixMatches(): void
    {
        $this->stream->expectLinePrefix('AUTH PLAIN ')->expectLinePrefix('MAIL FROM:');

        $this->stream->writeLine('AUTH PLAIN AHVzZXIAcGFzcw==', $this->deadline());

        $this->expectException(AssertionFailedError::class);

        $this->stream->writeLine('RCPT TO:<a@example.com>', $this->deadline());
    }

    /**
     * @return array<string, array{Closure(FakeSmtpStream, Deadline): mixed}>
     */
    public static function unexpectedCalls(): array
    {
        return [
            'read instead of a write' => [static fn (FakeSmtpStream $s, Deadline $d): string => $s->readLine($d)],
            'upgrade instead of a write' => [
                static fn (FakeSmtpStream $s, Deadline $d) => $s->upgradeToTls('x', null, $d),
            ],
            'open instead of a write' => [
                static fn (FakeSmtpStream $s, Deadline $d) => $s->open('192.0.2.1', 25, 'x', false, null, $d),
            ],
        ];
    }

    /**
     * @param Closure(FakeSmtpStream, Deadline): mixed $call
     */
    #[DataProvider('unexpectedCalls')]
    public function testUnexpectedCallFailsTheTest(Closure $call): void
    {
        $this->stream->expectLine('QUIT');

        $this->expectException(AssertionFailedError::class);

        $call($this->stream, $this->deadline());
    }

    public function testWriteWhenAReplyIsExpectedFailsTheTest(): void
    {
        $this->stream->reply("250 OK\r\n");

        $this->expectException(AssertionFailedError::class);

        $this->stream->writeLine('QUIT', $this->deadline());
    }

    public function testCallAfterTheScriptFailsTheTest(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->stream->readLine($this->deadline());
    }

    public function testCallAfterCloseFailsTheTest(): void
    {
        $this->stream->reply("250 OK\r\n");
        $this->stream->close();
        $this->stream->close();
        $this->assertTrue($this->stream->isClosed());

        $this->expectException(AssertionFailedError::class);

        $this->stream->readLine($this->deadline());
    }

    public function testReplyWithoutLineEndIsReturnedAsIs(): void
    {
        $this->stream->reply("250-first\r\n250 partial");

        $this->assertSame("250-first\r\n", $this->stream->readLine($this->deadline()));
        $this->assertSame('250 partial', $this->stream->readLine($this->deadline()));
        $this->stream->assertDone();
    }

    public function testLongLineIsCutAtTheReadLimit(): void
    {
        $limit = ReplyParser::MAX_LINE_BYTES + 2;
        $this->stream->reply('250 ' . str_repeat('x', $limit) . "\r\n");

        $this->assertSame($limit, strlen($this->stream->readLine($this->deadline())));
        $this->assertSame("xxxx\r\n", $this->stream->readLine($this->deadline()));
    }

    public function testDelayAdvancesTheClock(): void
    {
        $this->stream->reply("250 OK\r\n", 2.5);

        $this->stream->readLine($this->deadline());

        $this->assertSame(2.5, $this->clock->now());
    }

    public function testDelayIsAppliedOncePerReply(): void
    {
        $this->stream->reply("250-a\r\n250 b\r\n", 1.0);

        $this->stream->readLine($this->deadline());
        $this->stream->readLine($this->deadline());

        $this->assertSame(1.0, $this->clock->now());
    }

    public function testDelayBeyondTheDeadlineTimesOut(): void
    {
        $this->stream->reply("250 OK\r\n", 30.0);

        $this->assertFailure(StreamFailureKind::Timeout, fn () => $this->stream->readLine($this->deadline(10.0)));
        $this->assertSame(10.0, $this->clock->now());
    }

    public function testExpiredDeadlineTimesOutWithoutConsuming(): void
    {
        $this->stream->reply("250 OK\r\n");
        $deadline = $this->deadline(1.0);
        $this->clock->sleep(1.0);

        $this->assertFailure(StreamFailureKind::Timeout, fn () => $this->stream->readLine($deadline));
        $this->assertSame("250 OK\r\n", $this->stream->readLine($this->deadline()));
    }

    /**
     * @return array<string, array{StreamFailureKind}>
     */
    public static function kinds(): array
    {
        $cases = [];

        foreach (StreamFailureKind::cases() as $kind) {
            $cases[$kind->name] = [$kind];
        }

        return $cases;
    }

    #[DataProvider('kinds')]
    public function testFailureOnReadThrowsTheKind(StreamFailureKind $kind): void
    {
        $this->stream->fail($kind, 1.5);

        $this->assertFailure($kind, fn () => $this->stream->readLine($this->deadline()));
        $this->assertSame(1.5, $this->clock->now());
    }

    public function testFailureOnWrite(): void
    {
        $this->stream->fail(StreamFailureKind::Closed);

        $this->assertFailure(StreamFailureKind::Closed, fn () => $this->stream->writeLine('QUIT', $this->deadline()));
    }

    public function testFailureDelayBeyondTheDeadlineTimesOut(): void
    {
        $this->stream->fail(StreamFailureKind::Closed, 5.0);

        $this->assertFailure(StreamFailureKind::Timeout, fn () => $this->stream->readLine($this->deadline(2.0)));
        $this->assertSame(2.0, $this->clock->now());
    }

    public function testOpenFollowedByAFailureFails(): void
    {
        $this->stream->expectOpen()->fail(StreamFailureKind::Connect);

        $this->assertFailure(
            StreamFailureKind::Connect,
            fn () => $this->stream->open('192.0.2.1', 25, 'x', false, null, $this->deadline()),
        );
        $this->stream->assertDone();
    }

    /**
     * @return array<string, array{StreamFailureKind}>
     */
    public static function upgradeFailures(): array
    {
        return [
            'pending bytes' => [StreamFailureKind::UnexpectedData],
            'handshake' => [StreamFailureKind::Tls],
        ];
    }

    #[DataProvider('upgradeFailures')]
    public function testUpgradeFollowedByAFailureFails(StreamFailureKind $kind): void
    {
        $this->stream->expectUpgrade()->fail($kind);

        $failure = $this->assertFailure($kind, fn () => $this->stream->upgradeToTls('x', null, $this->deadline()));
        $this->stream->assertDone();

        if ($kind === StreamFailureKind::Tls) {
            $this->assertNotNull($failure->reason());
        }
    }

    public function testAssertDoneFailsWithEntriesLeft(): void
    {
        $this->stream->expectLine('QUIT');

        $this->expectException(AssertionFailedError::class);

        $this->stream->assertDone();
    }

    public function testAssertDoneFailsWithReplyBytesLeft(): void
    {
        $this->stream->reply("250-a\r\n250 b\r\n");
        $this->stream->readLine($this->deadline());

        $this->expectException(AssertionFailedError::class);

        $this->stream->assertDone();
    }

    private function deadline(float $seconds = 10.0): Deadline
    {
        return Deadline::start($this->clock, $seconds);
    }

    private function assertFailure(StreamFailureKind $kind, Closure $call): StreamFailure
    {
        try {
            $call();
        } catch (StreamFailure $failure) {
            $this->assertSame($kind, $failure->kind());

            return $failure;
        }

        $this->fail('expected a stream failure');
    }
}
