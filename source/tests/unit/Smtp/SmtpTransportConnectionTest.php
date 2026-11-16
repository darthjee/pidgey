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
use Pidgey\Smtp\TlsMode;
use Pidgey\Tests\Support\FakeSmtpStream;
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
final class SmtpTransportConnectionTest extends SmtpTransportTestCase
{
    public function testDeliversAMessageAndReturnsTheFinalReply(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $result = $this->send($stream);

        $this->assertSame(250, $result->code());
        $this->assertSame(['2.0.0 queued as ABC'], $result->reply()->lines());
        $this->assertSame(
            [['address' => '192.0.2.1', 'port' => 587, 'peerName' => self::HOST, 'implicitTls' => false,
                'caFile' => self::CA_FILE]],
            $stream->opens(),
        );
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    public function testDefaultStreamFactoryBuildsASocketStream(): void
    {
        $transport = new SmtpTransport($this->clock);
        $settings = new SessionSettings(1.0, 1.0, 0.0, null, null);

        $failure = $this->failure(fn () => $transport->send(
            new Target(self::HOST, '192.0.2.1', 25, TlsMode::Plain),
            $settings,
            null,
            $this->envelope(),
            self::MESSAGE,
        ));

        $this->assertSame(FailureCode::ConnectionFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Connect, $failure->step());
    }

    public function testStreamFactoryIsCalledOncePerSend(): void
    {
        $calls = 0;
        $streams = [$this->happyStream(), $this->happyStream()];
        $transport = new SmtpTransport($this->clock, static function () use (&$calls, $streams): FakeSmtpStream {
            return $streams[$calls++];
        });

        $transport->send($this->target(), $this->settings(), null, $this->envelope(), self::MESSAGE);
        $transport->send($this->target(), $this->settings(), null, $this->envelope(), self::MESSAGE);

        $this->assertSame(2, $calls);
    }

    /**
     * @return array<string, array{string, FailureCode}>
     */
    public static function greetings(): array
    {
        return [
            '554 rejected' => ["554 5.7.1 no thanks\r\n", FailureCode::Rejected],
            '421 busy' => ["421 4.3.2 busy\r\n", FailureCode::TemporaryFailure],
            '450 other 4xx' => ["450 later\r\n", FailureCode::TemporaryFailure],
            '550 other 5xx' => ["550 go away\r\n", FailureCode::Rejected],
            '250 not an error' => ["250 hello\r\n", FailureCode::ProtocolError],
        ];
    }

    #[DataProvider('greetings')]
    public function testGreetingOtherThan220Fails(string $raw, FailureCode $code): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->reply($raw);

        $failure = $this->sendFailure($stream);

        $this->assertSame($code, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
        $this->assertSame((int) substr($raw, 0, 3), $failure->reply()?->code());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    public function testCloseBeforeTheGreetingIsAConnectionFailure(): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->failRead(StreamFailureKind::Closed);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::ConnectionFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
        $this->assertNull($failure->reply());
        $this->assertInstanceOf(StreamFailure::class, $failure->getPrevious());
        $this->assertTrue($stream->isClosed());
    }

    public function testGreetingTimeoutIsATimeout(): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->reply("220 late\r\n", 10.5);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
        $this->assertSame(1010.0, $this->clock->now());
    }

    public function testOpenAndGreetingShareTheConnectTimeout(): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->reply("220 a\r\n", 6.0);

        $settings = new SessionSettings(5.0, 30.0, 120.0, self::CA_FILE, null);

        $this->assertSame(FailureCode::Timeout, $this->sendFailure($stream, settings: $settings)->failureCode());
        $this->assertSame(1005.0, $this->clock->now());
    }

    /**
     * @return array<string, array{StreamFailureKind, TlsMode, FailureCode}>
     */
    public static function openFailures(): array
    {
        return [
            'connect' => [StreamFailureKind::Connect, TlsMode::Plain, FailureCode::ConnectionFailed],
            'implicit TLS handshake' => [StreamFailureKind::Tls, TlsMode::Implicit, FailureCode::TlsFailed],
            'implicit TLS timeout' => [StreamFailureKind::Timeout, TlsMode::Implicit, FailureCode::Timeout],
            'plain connect timeout' => [StreamFailureKind::Timeout, TlsMode::Plain, FailureCode::ConnectionFailed],
        ];
    }

    #[DataProvider('openFailures')]
    public function testOpenFailureIsClassified(StreamFailureKind $kind, TlsMode $mode, FailureCode $code): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->fail($kind);

        $failure = $this->sendFailure($stream, $this->target($mode));

        $this->assertSame($code, $failure->failureCode());
        $this->assertSame(SmtpStep::Connect, $failure->step());
        $this->assertNull($failure->reply());
        $this->assertSame($kind === StreamFailureKind::Tls ? 'fake TLS failure' : null, $failure->reason());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    public function testExpiredSessionDeadlineFailsWithoutIo(): void
    {
        $stream = $this->stream();
        $stream->expectOpen();

        $settings = new SessionSettings(10.0, 30.0, 0.0, self::CA_FILE, null);
        $failure = $this->sendFailure($stream, settings: $settings);

        $this->assertSame(FailureCode::ConnectionFailed, $failure->failureCode());
        $this->assertSame([], $stream->opens());
        $this->assertTrue($stream->isClosed());
    }
}
