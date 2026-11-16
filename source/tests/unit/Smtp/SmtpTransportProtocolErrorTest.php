<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\SecretString;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Deadline;
use Pidgey\Smtp\EhloExtensions;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\FailureCode;
use Pidgey\Smtp\MalformedReply;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\SmtpSessionState;
use Pidgey\Smtp\SmtpStep;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\SocketStream;
use Pidgey\Smtp\StreamFailure;
use Pidgey\Smtp\StreamFailureKind;
use Pidgey\Smtp\Target;
use Pidgey\Tests\Support\SmtpTransportTestCase;

#[CoversClass(SmtpTransport::class)]
#[CoversClass(SmtpSessionState::class)]
#[UsesClass(Credentials::class)]
#[UsesClass(Deadline::class)]
#[UsesClass(EhloExtensions::class)]
#[UsesClass(Envelope::class)]
#[UsesClass(MalformedReply::class)]
#[UsesClass(Reply::class)]
#[UsesClass(ReplyParser::class)]
#[UsesClass(SecretString::class)]
#[UsesClass(SessionSettings::class)]
#[UsesClass(SmtpFailure::class)]
#[UsesClass(SmtpResult::class)]
#[UsesClass(SocketStream::class)]
#[UsesClass(StreamFailure::class)]
#[UsesClass(Target::class)]
final class SmtpTransportProtocolErrorTest extends SmtpTransportTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function malformedReplies(): array
    {
        return [
            'garbage' => ["hello\r\n"],
            'mixed codes' => ["250-a\r\n251 b\r\n"],
            'code out of range' => ["199 x\r\n"],
            'close mid-reply' => ["250-a\r\n"],
            'close mid-line' => ['250 no line end'],
        ];
    }

    #[DataProvider('malformedReplies')]
    public function testMalformedReplyIsAProtocolError(string $raw): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $stream->expectLine('MAIL FROM:<from@example.com>')->reply($raw);

        if (str_ends_with($raw, "-a\r\n")) {
            $stream->failRead(StreamFailureKind::Closed);
        }

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertNull($failure->reply());
        $this->assertInstanceOf(MalformedReply::class, $failure->getPrevious());
        $this->assertTrue($stream->isClosed());
    }

    public function testMalformedGreetingIsAProtocolError(): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->reply("SSH-2.0-OpenSSH\r\n");

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
    }

    public function testCloseWithNoReplyAfterTheGreetingIsAProtocolError(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $stream->expectLine('MAIL FROM:<from@example.com>')->reply("250 ok\r\n");
        $stream->expectLine('RCPT TO:<to@example.com>')->fail(StreamFailureKind::Closed);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::RcptTo, $failure->step());
        $this->assertNull($failure->reply());
        $stream->assertDone();
    }

    public function testWriteToAClosedPeerIsAProtocolError(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->fail(StreamFailureKind::Closed);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::Ehlo, $failure->step());
    }
}
