<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Pidgey\Tests\Support\FakeSmtpServer;
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
final class SmtpTransportTlsTest extends SmtpTransportIntegrationTestCase
{
    public function testStartTlsNotAdvertisedIsATlsFailure(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH PLAIN LOGIN\r\n"],
            ['expect' => 'QUIT', 'reply' => "221 bye\r\n"],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::StartTls, $this->canaryCredentials()));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertNull($failure->reply());
        $this->assertNoCanary($failure);
        $this->assertServerClean();
    }

    public function testInjectedBytesBeforeTheHandshakeAreATlsFailure(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 STARTTLS\r\n"],
            [
                'expect' => 'STARTTLS',
                'reply' => "220 go ahead\r\n",
                'then' => 'inject',
                'bytes' => "250-injected\r\n250 AUTH PLAIN\r\n",
            ],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::StartTls));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertNull($failure->reason());
        $this->assertServerClean();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badCertificates(): array
    {
        return [
            'untrusted' => ['untrusted'],
            'wrong name' => ['wrong-name'],
        ];
    }

    #[DataProvider('badCertificates')]
    public function testImplicitTlsWithABadCertificateIsATlsFailure(string $cert): void
    {
        $this->server = FakeSmtpServer::start([
            'mode' => 'ssl',
            'cert' => $cert,
            'steps' => [['reply' => "220 secure\r\n"]],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Implicit));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Connect, $failure->step());
        $this->assertNotEmpty($failure->reason());
        $this->assertSame('SMTP Connect: SMTP_TLS_FAILED', $failure->getMessage());
    }

    #[DataProvider('badCertificates')]
    public function testStartTlsWithABadCertificateIsATlsFailure(string $cert): void
    {
        $this->server = FakeSmtpServer::start([
            'cert' => $cert,
            'steps' => [
                ['reply' => "220 fake ready\r\n"],
                ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 STARTTLS\r\n"],
                ['expect' => 'STARTTLS', 'reply' => "220 go ahead\r\n", 'then' => 'starttls'],
            ],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::StartTls));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertNotEmpty($failure->reason());
    }

    public function testStartTlsSessionOnTheFakeServerDelivers(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 STARTTLS\r\n"],
            ['expect' => 'STARTTLS', 'reply' => "220 go ahead\r\n", 'then' => 'starttls'],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH LOGIN\r\n"],
            ['expect' => 'AUTH LOGIN', 'reply' => "334 VXNlcm5hbWU6\r\n"],
            ['expect' => base64_encode('user'), 'reply' => "334 UGFzc3dvcmQ6\r\n"],
            ['expect' => base64_encode(self::CANARY), 'reply' => "235 ok\r\n"],
            ...$this->envelopeSteps(),
            ['expect' => '.', 'reply' => "250 queued\r\n"],
            ['expect' => 'QUIT', 'reply' => "221 bye\r\n"],
        ]);

        $result = $this->sendToServer(TlsMode::StartTls, $this->canaryCredentials());

        $this->assertSame(250, $result->code());
        $this->assertServerClean();
    }
}
