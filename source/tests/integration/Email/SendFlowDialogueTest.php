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
final class SendFlowDialogueTest extends SendFlowIntegrationTestCase
{
    public function testEchoedAuthPayloadIsRedacted(): void
    {
        $payload = self::authPayload();
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH PLAIN LOGIN\r\n"],
            [
                'expect' => 'AUTH PLAIN ' . $payload,
                'reply' => "535-5.7.8 AUTH PLAIN {$payload}\r\n535 5.7.8 bad " . self::CANARY . "\r\n",
            ],
        ]);

        $connection = $this->fakeConnection(SmtpEncryption::Ssl);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_AUTH_FAILED', $outcome->failureCode());
        $this->assertSame('5.7.8 AUTH PLAIN [redacted] bad [redacted]', $outcome->replyMessage());
        $this->assertSame(
            $this->body(
                502,
                'SMTP_AUTH_FAILED',
                'The SMTP server rejected the credentials',
                535,
                '5.7.8 AUTH PLAIN [redacted] bad [redacted]',
            ),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }

    public function testRejectedRecipient(): void
    {
        $this->startServer([
            ...$this->authenticatedSteps(),
            ['expect' => 'MAIL FROM:<' . self::FROM . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'RCPT TO:<' . self::TO . '>', 'reply' => "550 5.1.1 User\tunknown\r\n"],
        ]);

        $connection = $this->fakeConnection(SmtpEncryption::Ssl);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame(
            $this->body(502, 'SMTP_REJECTED', 'The SMTP server rejected the recipient', 550, '5.1.1 User unknown'),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }

    public function testTemporaryFailureAtTheGreeting(): void
    {
        $this->startServer([['reply' => "421 4.3.2 busy\r\n"]]);

        $connection = $this->fakeConnection(SmtpEncryption::Ssl);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('greeting', $outcome->step());
        $this->assertSame(
            $this->body(
                503,
                'SMTP_TEMPORARY_FAILURE',
                'The SMTP server is temporarily unavailable; try again later',
                421,
                '4.3.2 busy',
            ),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }

    public function testMalformedReplyIsAProtocolError(): void
    {
        $this->startServer([
            ...$this->authenticatedSteps(),
            ['expect' => 'MAIL FROM:<' . self::FROM . '>', 'reply' => "250-ok\r\n251 mixed\r\n"],
        ]);

        $connection = $this->fakeConnection(SmtpEncryption::Ssl);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_PROTOCOL_ERROR', $outcome->failureCode());
        $this->assertSame('mail-from', $outcome->step());
        $this->assertSame(
            $this->body(502, 'SMTP_PROTOCOL_ERROR', 'The SMTP server sent an invalid reply'),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }
}
