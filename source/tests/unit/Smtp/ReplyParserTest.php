<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\Deadline;
use Pidgey\Smtp\MalformedReply;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SmtpStream;
use Pidgey\Smtp\StreamFailure;
use Pidgey\Smtp\StreamFailureKind;
use Pidgey\Tests\Support\FakeSmtpStream;
use Pidgey\Tests\Support\FrozenClock;

#[CoversClass(ReplyParser::class)]
#[UsesClass(Deadline::class)]
#[UsesClass(MalformedReply::class)]
#[UsesClass(Reply::class)]
#[UsesClass(StreamFailure::class)]
#[UsesClass(StreamFailureKind::class)]
#[UsesClass(FakeSmtpStream::class)]
#[UsesClass(FrozenClock::class)]
final class ReplyParserTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
    }

    /**
     * @return array<string, array{list<string>, integer, list<string>}>
     */
    public static function validReplies(): array
    {
        return [
            'single line' => [["250 OK\r\n"], 250, ['OK']],
            'code alone' => [["250\r\n"], 250, ['']],
            'empty continuation text' => [["250-\r\n", "250 done\r\n"], 250, ['', 'done']],
            'multi-line' => [
                ["250-mail.example.com\r\n", "250-SIZE 1000\r\n", "250 AUTH PLAIN\r\n"],
                250,
                ['mail.example.com', 'SIZE 1000', 'AUTH PLAIN'],
            ],
            'bare LF tolerated' => [["220 ready\n"], 220, ['ready']],
            'only one trailing CR stripped' => [["220 ready\r\r\n"], 220, ["ready\r"]],
            'lowest code' => [["200 ok\r\n"], 200, ['ok']],
            'highest code' => [["599 no\r\n"], 599, ['no']],
            'raw bytes kept' => [["550 caf\xC3\xA9 \x00 a\rb \xFF\r\n"], 550, ["caf\xC3\xA9 \x00 a\rb \xFF"]],
            'text keeps inner spacing' => [["250  two  spaces \r\n"], 250, [' two  spaces ']],
            'line at the byte limit' => [
                ['250 ' . str_repeat('x', ReplyParser::MAX_LINE_BYTES - 4) . "\r\n"],
                250,
                [str_repeat('x', ReplyParser::MAX_LINE_BYTES - 4)],
            ],
            'line at the byte limit with a bare LF' => [
                ['250 ' . str_repeat('x', ReplyParser::MAX_LINE_BYTES - 4) . "\n"],
                250,
                [str_repeat('x', ReplyParser::MAX_LINE_BYTES - 4)],
            ],
            'reply at the line limit' => [
                [...array_fill(0, ReplyParser::MAX_LINES - 1, "250-x\r\n"), "250 x\r\n"],
                250,
                array_fill(0, ReplyParser::MAX_LINES, 'x'),
            ],
        ];
    }

    /**
     * @param list<string> $lines
     * @param list<string> $texts
     */
    #[DataProvider('validReplies')]
    public function testValidReply(array $lines, int $code, array $texts): void
    {
        $reply = (new ReplyParser())->read($this->stream($lines), $this->budget());

        $this->assertSame($code, $reply->code());
        $this->assertSame($texts, $reply->lines());
    }

    /**
     * @return array<string, array{list<string|StreamFailureKind>}>
     */
    public static function malformedReplies(): array
    {
        return [
            'empty line' => [["\r\n"]],
            'bare LF only' => [["\n"]],
            'two digits' => [["25 OK\r\n"]],
            'four digits' => [["2500 OK\r\n"]],
            'letters' => [["abc OK\r\n"]],
            'leading space' => [[" 250 OK\r\n"]],
            'wrong separator' => [["250_OK\r\n"]],
            'tab separator' => [["250\tOK\r\n"]],
            'code below 200' => [["199 no\r\n"]],
            'code 100' => [["100 no\r\n"]],
            'code above 599' => [["600 no\r\n"]],
            'code 000' => [["000 no\r\n"]],
            'mixed codes' => [["250-first\r\n", "251 last\r\n"]],
            'malformed later line' => [["250-first\r\n", "oops\r\n"]],
            'line over the byte limit' => [['250 ' . str_repeat('x', ReplyParser::MAX_LINE_BYTES - 3) . "\r\n"]],
            'no LF within the limit' => [['250 ' . str_repeat('x', ReplyParser::MAX_LINE_BYTES - 2)]],
            'too many lines' => [array_fill(0, ReplyParser::MAX_LINES + 1, "250-x\r\n")],
            'closed mid-line' => [['250 partial']],
            'closed mid-line on a later line' => [["250-first\r\n", '250 part']],
            'closed mid-reply' => [["250-first\r\n", StreamFailureKind::Closed]],
            'empty read' => [['']],
        ];
    }

    /**
     * @param list<string|StreamFailureKind> $lines
     */
    #[DataProvider('malformedReplies')]
    public function testMalformedReply(array $lines): void
    {
        $this->expectException(MalformedReply::class);
        $this->expectExceptionMessage('malformed SMTP reply');

        (new ReplyParser())->read($this->stream($lines), $this->budget());
    }

    public function testTooManyLinesStopsReadingAtTheLimit(): void
    {
        $lines = array_fill(0, ReplyParser::MAX_LINES + 5, "250-x\r\n");
        $reads = 0;
        $stream = $this->createStub(SmtpStream::class);
        $stream->method('readLine')->willReturnCallback(static function () use (&$reads, $lines): string {
            return $lines[$reads++];
        });

        try {
            (new ReplyParser())->read($stream, $this->budget());
            $this->fail('expected a malformed reply');
        } catch (MalformedReply) {
            $this->assertSame(ReplyParser::MAX_LINES, $reads);
        }
    }

    /**
     * @return array<string, array{StreamFailureKind}>
     */
    public static function firstLineFailures(): array
    {
        return [
            'closed' => [StreamFailureKind::Closed],
            'timeout' => [StreamFailureKind::Timeout],
        ];
    }

    #[DataProvider('firstLineFailures')]
    public function testFailureOnTheFirstLinePropagates(StreamFailureKind $kind): void
    {
        try {
            (new ReplyParser())->read($this->stream([$kind]), $this->budget());
            $this->fail('expected a stream failure');
        } catch (StreamFailure $failure) {
            $this->assertSame($kind, $failure->kind());
        }
    }

    public function testTimeoutOnALaterLinePropagates(): void
    {
        try {
            (new ReplyParser())->read($this->stream(["250-first\r\n", StreamFailureKind::Timeout]), $this->budget());
            $this->fail('expected a stream failure');
        } catch (StreamFailure $failure) {
            $this->assertSame(StreamFailureKind::Timeout, $failure->kind());
        }
    }

    public function testTheBudgetBoundsTheWholeReplyNotEachLine(): void
    {
        $stream = (new FakeSmtpStream($this->clock))
            ->reply("250-first\r\n", 6.0)
            ->reply("250 last\r\n", 6.0);

        try {
            (new ReplyParser())->read($stream, $this->budget());
            $this->fail('expected a timeout');
        } catch (StreamFailure $failure) {
            $this->assertSame(StreamFailureKind::Timeout, $failure->kind());
            $this->assertSame(10.0, $this->clock->now());
        }
    }

    public function testEveryLineGetsTheSameReplyBudget(): void
    {
        $budget = $this->budget();
        $stream = $this->createMock(SmtpStream::class);
        $stream->expects($this->exactly(2))
            ->method('readLine')
            ->with($this->identicalTo($budget))
            ->willReturnOnConsecutiveCalls("250-first\r\n", "250 last\r\n");

        (new ReplyParser())->read($stream, $budget);
    }

    public function testReadsOneReplyOnly(): void
    {
        $stream = $this->stream(["250 first\r\n", "251 second\r\n"]);
        $parser = new ReplyParser();

        $this->assertSame(250, $parser->read($stream, $this->budget())->code());
        $this->assertSame(251, $parser->read($stream, $this->budget())->code());
    }

    /**
     * @param list<string|StreamFailureKind> $lines Raw reads; a kind is thrown as a failure.
     */
    private function stream(array $lines): SmtpStream
    {
        $stream = new FakeSmtpStream($this->clock);

        foreach ($lines as $line) {
            if ($line instanceof StreamFailureKind) {
                $stream->fail($line);
            } else {
                $stream->reply($line);
            }
        }

        return $stream;
    }

    private function budget(): Deadline
    {
        return Deadline::start($this->clock, 10.0);
    }
}
