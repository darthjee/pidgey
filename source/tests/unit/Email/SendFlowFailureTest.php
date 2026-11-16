<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Smtp\FailureCode;
use Pidgey\Smtp\SmtpStep;
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
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\SecretString;
use Pidgey\Model\SmtpConnection;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\HostCheck;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\Target;
use Pidgey\Tests\Support\SendFlowTestCase;

#[CoversClass(SendFlow::class)]
#[CoversClass(SendOutcome::class)]
#[CoversClass(SendActor::class)]
#[UsesClass(ReplyText::class)]
#[UsesClass(Message::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(HostPolicy::class)]
#[UsesClass(HostCheck::class)]
#[UsesClass(PasswordCipher::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(DecryptionFailed::class)]
#[UsesClass(SecretString::class)]
#[UsesClass(Credentials::class)]
#[UsesClass(Envelope::class)]
#[UsesClass(Target::class)]
#[UsesClass(SessionSettings::class)]
#[UsesClass(SmtpFailure::class)]
#[UsesClass(SmtpResult::class)]
#[UsesClass(Reply::class)]
#[UsesClass(SmtpConnection::class)]
#[UsesClass(LoggerFactory::class)]
final class SendFlowFailureTest extends SendFlowTestCase
{
    /**
     * @return iterable<string, array{0: SmtpFailure, 1: string, 2: string, 3: ?int, 4: ?string, 5: ?string}>
     */
    public static function failures(): iterable
    {
        yield 'connection failed' => [
            new SmtpFailure(FailureCode::ConnectionFailed, SmtpStep::Connect),
            'SMTP_CONNECTION_FAILED', 'connect', null, null, null,
        ];
        yield 'tls failed with reason' => [
            new SmtpFailure(FailureCode::TlsFailed, SmtpStep::StartTls, null, 'certificate verify failed'),
            'SMTP_TLS_FAILED', 'starttls', null, null, 'certificate verify failed',
        ];
        yield 'auth failed' => [
            new SmtpFailure(FailureCode::AuthFailed, SmtpStep::Auth, new Reply(535, ['5.7.8 Bad credentials'])),
            'SMTP_AUTH_FAILED', 'auth', 535, '5.7.8 Bad credentials', null,
        ];
        yield 'greeting rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::Greeting, new Reply(554, ['No'])),
            'SMTP_REJECTED', 'greeting', 554, 'No', null,
        ];
        yield 'ehlo rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::Ehlo, new Reply(550, ['No'])),
            'SMTP_REJECTED', 'ehlo', 550, 'No', null,
        ];
        yield 'sender rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::MailFrom, new Reply(553, ['Bad sender'])),
            'SMTP_REJECTED', 'mail-from', 553, 'Bad sender', null,
        ];
        yield 'recipient rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::RcptTo, new Reply(550, ['5.1.1 User unknown'])),
            'SMTP_REJECTED', 'rcpt-to', 550, '5.1.1 User unknown', null,
        ];
        yield 'data rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::Data, new Reply(554, ['No data'])),
            'SMTP_REJECTED', 'data', 554, 'No data', null,
        ];
        yield 'message rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::EndOfData, new Reply(552, ['Too big'])),
            'SMTP_REJECTED', 'end-of-data', 552, 'Too big', null,
        ];
        yield 'temporary failure' => [
            new SmtpFailure(FailureCode::TemporaryFailure, SmtpStep::RcptTo, new Reply(451, ['Try later'])),
            'SMTP_TEMPORARY_FAILURE', 'rcpt-to', 451, 'Try later', null,
        ];
        yield 'protocol error' => [
            new SmtpFailure(FailureCode::ProtocolError, SmtpStep::Ehlo),
            'SMTP_PROTOCOL_ERROR', 'ehlo', null, null, null,
        ];
        yield 'timeout' => [
            new SmtpFailure(FailureCode::Timeout, SmtpStep::Greeting),
            'SMTP_TIMEOUT', 'greeting', null, null, null,
        ];
    }

    #[DataProvider('failures')]
    public function testMapsEachTransportFailure(
        SmtpFailure $failure,
        string $code,
        string $step,
        ?int $replyCode,
        ?string $reply,
        ?string $tlsReason,
    ): void {
        $flow = $this->flow(static fn (): never => throw $failure);

        $outcome = $flow->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertFalse($outcome->isSent());
        $this->assertSame($code, $outcome->failureCode());
        $this->assertSame($step, $outcome->step());
        $this->assertSame($replyCode, $outcome->replyCode());
        $this->assertSame($reply, $outcome->replyMessage());
        $this->assertSame($tlsReason, $outcome->tlsReason());
    }

    public function testRedactsTheEchoedPasswordInAFailureReply(): void
    {
        $payload = base64_encode("\0" . self::USERNAME . "\0" . self::PASSWORD);
        $failure = new SmtpFailure(
            FailureCode::AuthFailed,
            SmtpStep::Auth,
            new Reply(535, ["5.7.8 {$payload} / " . self::PASSWORD . ' / ' . base64_encode(self::PASSWORD)]),
        );

        $outcome = $this->flow(static fn (): never => throw $failure)
            ->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertSame('5.7.8 [redacted] / [redacted] / [redacted]', $outcome->replyMessage());
        $this->log->assertNotLogged(self::PASSWORD);
    }

    public function testRedactsTheEchoedPasswordInASuccessReply(): void
    {
        $flow = $this->flow(static fn (): SmtpResult => new SmtpResult(new Reply(250, ['Ok ' . self::PASSWORD])));

        $outcome = $flow->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertSame('Ok [redacted]', $outcome->replyMessage());
    }
}
