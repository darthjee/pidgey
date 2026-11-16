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
final class SmtpTransportEhloTest extends SmtpTransportTestCase
{
    public function testEhloUsesTheConfiguredName(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->expectLine('EHLO mta.pidgey.test')->reply("250 mx\r\n");
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, settings: new SessionSettings(10.0, 30.0, 120.0, null, 'mta.pidgey.test'));

        $stream->assertDone();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function localAddresses(): array
    {
        return [
            'IPv4' => ['203.0.113.7', 'EHLO [203.0.113.7]'],
            'IPv6' => ['2001:db8::7', 'EHLO [IPv6:2001:db8::7]'],
        ];
    }

    #[DataProvider('localAddresses')]
    public function testEhloFallsBackToTheLocalAddressLiteral(string $local, string $line): void
    {
        $stream = $this->stream($local);
        $this->greet($stream);
        $stream->expectLine($line)->reply("250 mx\r\n");
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, settings: new SessionSettings(10.0, 30.0, 120.0, null, ''));

        $stream->assertDone();
    }

    /**
     * @return array<string, array{string, FailureCode}>
     */
    public static function ehloRejections(): array
    {
        return [
            '550' => ["550 5.7.1 go away\r\n", FailureCode::Rejected],
            '421' => ["421 4.3.2 later\r\n", FailureCode::TemporaryFailure],
            '502 unknown command' => ["502 5.5.2 what\r\n", FailureCode::Rejected],
        ];
    }

    #[DataProvider('ehloRejections')]
    public function testEhloRejectionHasNoHeloFallback(string $raw, FailureCode $code): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->expectLine(self::EHLO)->reply($raw);

        $failure = $this->sendFailure($stream);

        $this->assertSame($code, $failure->failureCode());
        $this->assertSame(SmtpStep::Ehlo, $failure->step());
        $this->assertNotNull($failure->reply());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    public function testPostTlsExtensionsReplaceThePreTlsList(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS', 'AUTH PLAIN');
        $stream->expectLine('STARTTLS')->reply("220 go ahead\r\n")->expectUpgrade();
        $this->ehlo($stream, 'AUTH LOGIN');
        $this->login($stream);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, $this->target(TlsMode::StartTls), $this->credentials());

        $stream->assertDone();
    }

    public function testPostTlsEhloRejectionIsClassifiedAtEhlo(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS');
        $stream->expectLine('STARTTLS')->reply("220 go ahead\r\n")->expectUpgrade();
        $stream->expectLine(self::EHLO)->reply("554 no\r\n");

        $failure = $this->sendFailure($stream, $this->target(TlsMode::StartTls));

        $this->assertSame(FailureCode::Rejected, $failure->failureCode());
        $this->assertSame(SmtpStep::Ehlo, $failure->step());
    }
}
