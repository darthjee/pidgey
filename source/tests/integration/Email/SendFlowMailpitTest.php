<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Email;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Crypto\DecryptionFailed;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Email\Message;
use Pidgey\Email\MimeBuilder;
use Pidgey\Email\ReplyText;
use Pidgey\Email\SendActor;
use Pidgey\Email\SendFlow;
use Pidgey\Email\SendOutcome;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;
use Pidgey\Http\SmtpFailureException;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\SecretString;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Deadline;
use Pidgey\Smtp\EhloExtensions;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\HostCheck;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\MalformedReply;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\SmtpSessionState;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\SocketStream;
use Pidgey\Smtp\StreamFailure;
use Pidgey\Smtp\SystemHostResolver;
use Pidgey\Smtp\Target;
use Pidgey\Smtp\TlsContext;
use Pidgey\Tests\Support\Mailpit;
use Pidgey\Tests\Support\SendFlowIntegrationTestCase;

#[CoversClass(SendFlow::class)]
#[CoversClass(SmtpFailureException::class)]
#[UsesClass(AppPath::class)]
#[UsesClass(Credentials::class)]
#[UsesClass(Deadline::class)]
#[UsesClass(DecryptionFailed::class)]
#[UsesClass(EhloExtensions::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(Env::class)]
#[UsesClass(Envelope::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(HostCheck::class)]
#[UsesClass(HostPolicy::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(LoggerFactory::class)]
#[UsesClass(MalformedReply::class)]
#[UsesClass(Message::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(PasswordCipher::class)]
#[UsesClass(Reply::class)]
#[UsesClass(ReplyParser::class)]
#[UsesClass(ReplyText::class)]
#[UsesClass(Response::class)]
#[UsesClass(SecretString::class)]
#[UsesClass(SendActor::class)]
#[UsesClass(SendOutcome::class)]
#[UsesClass(SessionSettings::class)]
#[UsesClass(SmtpConnection::class)]
#[UsesClass(SmtpFailure::class)]
#[UsesClass(SmtpResult::class)]
#[UsesClass(SmtpSessionState::class)]
#[UsesClass(SmtpTransport::class)]
#[UsesClass(SocketStream::class)]
#[UsesClass(StreamFailure::class)]
#[UsesClass(SystemHostResolver::class)]
#[UsesClass(Target::class)]
#[UsesClass(TlsContext::class)]
final class SendFlowMailpitTest extends SendFlowIntegrationTestCase
{
    /**
     * @return array<string, array{string, SmtpEncryption, bool}>
     */
    public static function mailpitDeliveries(): array
    {
        return [
            'plain, no credentials' => ['plain', SmtpEncryption::None, false],
            'STARTTLS with credentials' => ['starttls', SmtpEncryption::Tls, true],
            'implicit TLS with credentials' => ['ssl', SmtpEncryption::Ssl, true],
        ];
    }

    #[DataProvider('mailpitDeliveries')]
    public function testDeliversThroughMailpit(string $instance, SmtpEncryption $encryption, bool $authenticate): void
    {
        $mailpit = Mailpit::$instance();
        $connection = $this->connection(
            $mailpit->host(),
            $mailpit->smtpPort(),
            $encryption,
            $authenticate ? Mailpit::AUTH_USER : null,
            $authenticate ? Mailpit::AUTH_PASSWORD : null,
        );

        $outcome = $this->flow($this->mailpitSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertTrue($outcome->isSent());
        $this->assertSame(250, $outcome->replyCode());
        $this->assertNotSame('', (string) $outcome->replyMessage());

        $messages = $mailpit->messages();
        $this->assertCount(1, $messages);
        $message = $mailpit->message((string) $messages[0]['ID']);
        $this->assertSame(self::SUBJECT, $message['Subject']);
        $this->assertSame(self::FROM, $message['From']['Address']);
        $this->assertSame(self::TO, $message['To'][0]['Address']);
        $this->assertSame(self::TEXT, trim((string) $message['Text']));
        $this->assertSame(self::HTML, trim((string) $message['HTML']));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}@pidgey\.test$/', (string) $message['MessageID']);

        $this->assertLogged('info', [
            'organizationId' => self::ORGANIZATION,
            'connectionId' => self::CONNECTION,
            'userId' => 3,
            'recipient' => self::TO,
            'outcome' => 'sent',
            'replyCode' => 250,
            'reply' => $outcome->replyMessage(),
        ]);
    }

    public function testWrongCredentialsAreAnAuthFailure(): void
    {
        $mailpit = Mailpit::starttls();
        $connection = $this->connection(
            $mailpit->host(),
            $mailpit->smtpPort(),
            SmtpEncryption::Tls,
            Mailpit::AUTH_USER,
        );

        $outcome = $this->flow($this->mailpitSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_AUTH_FAILED', $outcome->failureCode());
        $this->assertSame('auth', $outcome->step());
        $this->assertSame(535, $outcome->replyCode());
        $reply = $outcome->replyMessage();
        $this->assertNotNull($reply);
        $this->assertSame(
            $this->body(502, 'SMTP_AUTH_FAILED', 'The SMTP server rejected the credentials', 535, $reply),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection, Mailpit::AUTH_USER);
        $this->assertSame([], $mailpit->messages());
    }

    public function testNoCredentialsOnTheAuthInstanceRequireAuthentication(): void
    {
        $mailpit = Mailpit::starttls();
        $connection = $this->connection($mailpit->host(), $mailpit->smtpPort(), SmtpEncryption::Tls, null, null);

        $outcome = $this->flow($this->mailpitSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_AUTH_FAILED', $outcome->failureCode());
        $this->assertSame('mail-from', $outcome->step());
        $this->assertSame(530, $outcome->replyCode());
        $reply = $outcome->replyMessage();
        $this->assertNotNull($reply);
        $this->assertSame(
            $this->body(502, 'SMTP_AUTH_FAILED', 'The SMTP server requires authentication', 530, $reply),
            $this->formatted($outcome, $connection),
        );
    }

    public function testSendsWithTheCanaryAndRedactsItsEcho(): void
    {
        $this->startServer([
            ...$this->authenticatedSteps(),
            ['expect' => 'MAIL FROM:<' . self::FROM . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'RCPT TO:<' . self::TO . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'DATA', 'reply' => "354 go\r\n"],
            ['expectPrefix' => 'Date: '],
            ...$this->skipUntilDot(),
            ['expect' => '.', 'reply' => '250 2.0.0 queued for ' . self::CANARY . "\r\n"],
            ['expect' => 'QUIT', 'reply' => "221 bye\r\n"],
        ]);

        $connection = $this->fakeConnection(SmtpEncryption::Ssl);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::token(8));

        $this->assertTrue($outcome->isSent());
        $this->assertSame('2.0.0 queued for [redacted]', $outcome->replyMessage());
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }
}
