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
final class SmtpTransportTlsTest extends SmtpTransportTestCase
{
    public function testStartTlsUpgradesThenSendsEhloAgain(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS');
        $stream->expectLine('STARTTLS')->reply("220 2.0.0 go ahead\r\n")->expectUpgrade();
        $this->ehlo($stream);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, $this->target(TlsMode::StartTls));

        $this->assertSame([['peerName' => self::HOST, 'caFile' => self::CA_FILE]], $stream->upgrades());
        $this->assertFalse($stream->opens()[0]['implicitTls']);
        $stream->assertDone();
    }

    public function testStartTlsNotAdvertisedFailsAndQuits(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN');
        $this->quit($stream);

        $failure = $this->sendFailure($stream, $this->target(TlsMode::StartTls), $this->credentials());

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertNull($failure->reply());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
        $this->assertNoCanary($failure);
    }

    public function testStartTlsNotAdvertisedFailsEvenWhenQuitFails(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $stream->expectLine('QUIT')->fail(StreamFailureKind::Closed);

        $failure = $this->sendFailure($stream, $this->target(TlsMode::StartTls));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $stream->assertDone();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function startTlsRefusals(): array
    {
        return [
            '454' => ["454 4.7.0 TLS not available\r\n"],
            '502' => ["502 5.5.1 not implemented\r\n"],
            '250' => ["250 ok\r\n"],
        ];
    }

    #[DataProvider('startTlsRefusals')]
    public function testStartTlsRefusedIsATlsFailureWithTheReply(string $raw): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS');
        $stream->expectLine('STARTTLS')->reply($raw);

        $failure = $this->sendFailure($stream, $this->target(TlsMode::StartTls));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertSame((int) substr($raw, 0, 3), $failure->reply()?->code());
        $this->assertSame([], $stream->upgrades());
        $stream->assertDone();
    }

    /**
     * @return array<string, array{StreamFailureKind, FailureCode, string|null}>
     */
    public static function upgradeFailures(): array
    {
        return [
            'handshake' => [StreamFailureKind::Tls, FailureCode::TlsFailed, 'fake TLS failure'],
            'injected bytes' => [StreamFailureKind::UnexpectedData, FailureCode::TlsFailed, null],
            'handshake timeout' => [StreamFailureKind::Timeout, FailureCode::Timeout, null],
        ];
    }

    #[DataProvider('upgradeFailures')]
    public function testUpgradeFailureClosesWithoutQuit(
        StreamFailureKind $kind,
        FailureCode $code,
        ?string $reason,
    ): void {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS');
        $stream->expectLine('STARTTLS')->reply("220 go ahead\r\n")->expectUpgrade()->fail($kind);

        $failure = $this->sendFailure($stream, $this->target(TlsMode::StartTls));

        $this->assertSame($code, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertNull($failure->reply());
        $this->assertSame($reason, $failure->reason());
        $this->assertStringNotContainsString('fake TLS failure', $failure->getMessage());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    public function testImplicitTlsHandshakesAtOpenAndSendsNoStartTls(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS');
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, $this->target(TlsMode::Implicit));

        $this->assertTrue($stream->opens()[0]['implicitTls']);
        $this->assertSame(self::HOST, $stream->opens()[0]['peerName']);
        $this->assertSame([], $stream->upgrades());
        $stream->assertDone();
    }
}
