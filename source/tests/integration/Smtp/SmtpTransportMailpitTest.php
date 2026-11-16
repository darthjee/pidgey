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
use Pidgey\Tests\Support\Mailpit;
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
final class SmtpTransportMailpitTest extends SmtpTransportIntegrationTestCase
{
    /**
     * @return array<string, array{string, TlsMode, bool}>
     */
    public static function mailpitDeliveries(): array
    {
        return [
            'plain, no credentials' => ['plain', TlsMode::Plain, false],
            'STARTTLS with credentials' => ['starttls', TlsMode::StartTls, true],
            'implicit TLS with credentials' => ['ssl', TlsMode::Implicit, true],
        ];
    }

    #[DataProvider('mailpitDeliveries')]
    public function testMailpitDeliversTheMessage(string $instance, TlsMode $mode, bool $authenticate): void
    {
        $mailpit = Mailpit::$instance();
        $subject = "transport {$instance}";
        $credentials = $authenticate
            ? new Credentials(Mailpit::AUTH_USER, new SecretString(Mailpit::AUTH_PASSWORD))
            : null;

        $result = $this->sendTo($mailpit, $mode, $credentials, $this->message($subject));

        $this->assertSame(250, $result->code());
        $messages = $mailpit->messages();
        $this->assertCount(1, $messages);
        $this->assertSame($subject, $messages[0]['Subject'] ?? null);

        $text = (string) ($mailpit->message((string) $messages[0]['ID'])['Text'] ?? '');
        $lines = preg_split('/\r\n|\n/', $text) ?: [];
        $this->assertContains(self::DOT_LINE, $lines);
        $this->assertContains('.', $lines);
        $this->assertNotContains('..', $lines);
    }

    public function testMailpitWrongPasswordIsAnAuthFailure(): void
    {
        $credentials = new Credentials(Mailpit::AUTH_USER, new SecretString(self::CANARY));

        $failure = $this->failure(fn () => $this->sendTo(Mailpit::starttls(), TlsMode::StartTls, $credentials));

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Auth, $failure->step());
        $this->assertNotNull($failure->reply());
        $this->assertNoCanary($failure, Mailpit::AUTH_USER);
        $this->assertSame([], Mailpit::starttls()->messages());
    }

    public function testMailpitWithoutCredentialsIsAnAuthFailureAtMailFrom(): void
    {
        $failure = $this->failure(fn () => $this->sendTo(Mailpit::starttls(), TlsMode::StartTls, null));

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertSame(530, $failure->reply()?->code());
    }

    public function testImplicitTlsAgainstThePlainPortIsATlsFailure(): void
    {
        $failure = $this->failure(fn () => $this->sendTo(Mailpit::plain(), TlsMode::Implicit, null));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Connect, $failure->step());
        $this->assertNotNull($failure->reason());
    }

    public function testPlainAgainstTheSslPortIsATimeout(): void
    {
        $settings = $this->mailpitSettings(self::SHORT_TIMEOUT);

        $failure = $this->failure(fn () => $this->sendTo(Mailpit::ssl(), TlsMode::Plain, null, settings: $settings));

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
    }
}
