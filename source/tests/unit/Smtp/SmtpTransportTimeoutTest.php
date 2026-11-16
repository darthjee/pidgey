<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
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
final class SmtpTransportTimeoutTest extends SmtpTransportTestCase
{
    public function testReplyBeyondTheCommandTimeoutIsATimeout(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $stream->expectLine('MAIL FROM:<from@example.com>')->reply("250 ok\r\n", 30.5);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertSame(1030.0, $this->clock->now());
    }

    public function testReplyJustWithinTheCommandTimeoutSucceeds(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->expectLine(self::EHLO)->reply("250 mx\r\n", 30.0);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream);

        $stream->assertDone();
    }

    public function testSessionDeadlineCutsALaterReplyShort(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->expectLine(self::EHLO)->reply("250 mx\r\n", 25.0);
        $stream->expectLine('MAIL FROM:<from@example.com>')->reply("250 ok\r\n", 26.0);

        $settings = new SessionSettings(10.0, 30.0, 50.0, self::CA_FILE, null);
        $failure = $this->sendFailure($stream, settings: $settings);

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertSame(1050.0, $this->clock->now());
    }

    public function testExpiredSessionDeadlineFailsTheNextWriteWithoutIo(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->expectLine(self::EHLO)->reply("250 mx\r\n", 20.0);
        $stream->expectLine('MAIL FROM:<from@example.com>');

        $settings = new SessionSettings(10.0, 30.0, 20.0, self::CA_FILE, null);
        $failure = $this->sendFailure($stream, settings: $settings);

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertSame(1020.0, $this->clock->now());
        $this->assertTrue($stream->isClosed());
    }

    public function testFinalReplyTimeoutIsATimeoutAtEndOfData(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $this->envelopeAndData($stream, ['Subject: hi', '', 'body'], "250 late\r\n", 31.0);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::EndOfData, $failure->step());
    }
}
