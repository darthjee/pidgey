<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Email;

use PHPUnit\Framework\Attributes\CoversClass;
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
use Pidgey\Smtp\SocketStream;
use Pidgey\Smtp\StreamFailure;
use Pidgey\Smtp\SystemHostResolver;
use Pidgey\Smtp\Target;
use Pidgey\Smtp\TlsContext;
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
final class SendFlowConnectionTest extends SendFlowIntegrationTestCase
{
    public function testClosedPortIsAConnectionFailure(): void
    {
        $connection = $this->connection('localhost', $this->closedPort(), SmtpEncryption::Ssl, self::USERNAME);

        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_CONNECTION_FAILED', $outcome->failureCode());
        $this->assertSame(
            $this->body(502, 'SMTP_CONNECTION_FAILED', 'Could not connect to the SMTP server'),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
    }

    public function testStartTlsNotAdvertisedIsATlsFailure(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH PLAIN\r\n"],
            ['expect' => 'QUIT', 'reply' => "221 bye\r\n"],
        ], 'plain');

        $connection = $this->fakeConnection(SmtpEncryption::Tls);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_TLS_FAILED', $outcome->failureCode());
        $this->assertSame('starttls', $outcome->step());
        $this->assertSame(
            $this->body(502, 'SMTP_TLS_FAILED', 'TLS negotiation with the SMTP server failed'),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }

    public function testSilentServerIsATimeoutWithThePortHint(): void
    {
        $this->startServer([['then' => 'hang']], 'plain');

        $connection = $this->connection('localhost', (int) $this->server?->port(), SmtpEncryption::Tls, self::USERNAME);
        $short = self::SHORT_TIMEOUT;
        $settings = new SessionSettings($short, $short, self::TIMEOUT, self::FIXTURE_CA, self::EHLO_NAME);
        $outcome = $this->flow($settings)->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_TIMEOUT', $outcome->failureCode());
        $this->assertSame('greeting', $outcome->step());
        $this->assertSame(
            $this->body(504, 'SMTP_TIMEOUT', 'The SMTP server timed out'),
            $this->formatted($outcome, $connection),
        );
        $this->assertSame(
            $this->body(504, 'SMTP_TIMEOUT', 'The SMTP server timed out; port 465 usually requires encryption ssl'),
            $this->json(SmtpFailureException::fromOutcome($outcome, 465, SmtpEncryption::Tls)),
        );
        $this->assertNoCanary($outcome, $connection);
    }
}
