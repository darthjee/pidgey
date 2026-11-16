<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\Env;
use Pidgey\Infra\SecretString;
use Pidgey\Infra\SystemClock;
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
use Pidgey\Smtp\TlsContext;
use Pidgey\Smtp\TlsMode;
use Pidgey\Tests\Support\SmtpTransportIntegrationTestCase;

#[CoversClass(SmtpTransport::class)]
#[CoversClass(SmtpSessionState::class)]
#[UsesClass(AppPath::class)]
#[UsesClass(Credentials::class)]
#[UsesClass(Deadline::class)]
#[UsesClass(EhloExtensions::class)]
#[UsesClass(Env::class)]
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
#[UsesClass(StreamFailureKind::class)]
#[UsesClass(SystemClock::class)]
#[UsesClass(Target::class)]
#[UsesClass(TlsContext::class)]
final class SmtpTransportConnectionTest extends SmtpTransportIntegrationTestCase
{
    public function testHangingGreetingIsATimeout(): void
    {
        $this->startServer([['then' => 'hang']]);
        $settings = $this->fakeSettings(connectTimeout: self::SHORT_TIMEOUT);
        $started = microtime(true);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Plain, settings: $settings));

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
        $this->assertLessThan(self::SHORT_TIMEOUT + 1.0, microtime(true) - $started);
    }

    public function testHangingReplyIsBoundByTheCommandTimeout(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'then' => 'hang'],
        ]);
        $settings = $this->fakeSettings(commandTimeout: self::SHORT_TIMEOUT);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Plain, settings: $settings));

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::Ehlo, $failure->step());
    }

    public function testCloseBeforeTheGreetingIsAConnectionFailure(): void
    {
        $this->startServer([['then' => 'close']]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Plain));

        $this->assertSame(FailureCode::ConnectionFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
        $this->assertServerClean();
    }

    public function testClosedPortIsAConnectionFailure(): void
    {
        $target = new Target('localhost', '127.0.0.1', $this->closedPort(), TlsMode::Plain);

        $failure = $this->failure(fn () => $this->transport->send(
            $target,
            $this->fakeSettings(),
            null,
            new Envelope(self::FROM, [self::TO]),
            $this->message('closed'),
        ));

        $this->assertSame(FailureCode::ConnectionFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Connect, $failure->step());
    }
}
