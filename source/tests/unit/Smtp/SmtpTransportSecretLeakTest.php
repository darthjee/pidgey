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
final class SmtpTransportSecretLeakTest extends SmtpTransportTestCase
{
    public function testAuthWriteFailureLeaksNoSecret(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN');
        $stream->fail(StreamFailureKind::Closed);

        $failure = $this->sendFailure($stream, credentials: $this->credentials());

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::Auth, $failure->step());
        $this->assertNoCanary($failure);
    }

    public function testSuccessWithCredentialsLeaksNoSecret(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN');
        $stream->expectLine('AUTH PLAIN ' . $this->plainPayload())->reply("235 ok\r\n");
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $result = $this->send($stream, credentials: $this->credentials());

        $this->assertStringNotContainsString(self::CANARY, print_r($result, true));
    }

    public function testCanaryCheckDetectsALeak(): void
    {
        $previous = new RuntimeException(self::CANARY);
        $leak = new SmtpFailure(FailureCode::AuthFailed, SmtpStep::Auth, null, null, $previous);

        $this->assertNotEmpty($this->leaks($leak));
    }
}
