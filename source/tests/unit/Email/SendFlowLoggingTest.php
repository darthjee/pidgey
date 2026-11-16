<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

use Monolog\Level;
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
final class SendFlowLoggingTest extends SendFlowTestCase
{
    public function testLogsOneInfoLineWhenSent(): void
    {
        $flow = $this->flow(static fn (): SmtpResult => new SmtpResult(new Reply(250, ['2.0.0 Ok'])));

        $flow->send($this->connection(), $this->message(), SendActor::token(42));

        $records = $this->log->records();
        $this->assertCount(1, $records);
        $this->assertSame('smtp-send-attempt', $records[0]->message);
        $this->assertSame(Level::Info, $records[0]->level);
        $this->assertSame([
            'organizationId' => self::ORGANIZATION,
            'connectionId' => self::CONNECTION,
            'tokenId' => 42,
            'recipient' => self::RECIPIENT,
            'outcome' => 'sent',
            'replyCode' => 250,
            'reply' => '2.0.0 Ok',
        ], $records[0]->context);
    }

    public function testLogsOneWarningLineWhenFailed(): void
    {
        $failure = new SmtpFailure(FailureCode::TlsFailed, SmtpStep::Connect, null, 'certificate verify failed');

        $this->flow(static fn (): never => throw $failure)
            ->send($this->connection(), $this->message(), SendActor::user(9));

        $records = $this->log->records();
        $this->assertCount(1, $records);
        $this->assertSame(Level::Warning, $records[0]->level);
        $this->assertSame([
            'organizationId' => self::ORGANIZATION,
            'connectionId' => self::CONNECTION,
            'userId' => 9,
            'recipient' => self::RECIPIENT,
            'outcome' => 'SMTP_TLS_FAILED',
            'step' => 'connect',
            'tlsReason' => 'certificate verify failed',
        ], $records[0]->context);
    }

    public function testLogsTheReplyOfAFailure(): void
    {
        $failure = new SmtpFailure(FailureCode::Rejected, SmtpStep::RcptTo, new Reply(550, ['5.1.1 Unknown']));

        $this->flow(static fn (): never => throw $failure)
            ->send($this->connection(), $this->message(), SendActor::user(9));

        $context = $this->log->records()[0]->context;
        $this->assertSame(550, $context['replyCode']);
        $this->assertSame('5.1.1 Unknown', $context['reply']);
        $this->assertSame('rcpt-to', $context['step']);
    }

    public function testLogsASendTimeFailure(): void
    {
        $this->flow($this->ok())
            ->send($this->connection(host: '127.0.0.1'), $this->message(), SendActor::user(9));

        $records = $this->log->records();
        $this->assertCount(1, $records);
        $this->assertSame(Level::Warning, $records[0]->level);
        $this->assertSame([
            'organizationId' => self::ORGANIZATION,
            'connectionId' => self::CONNECTION,
            'userId' => 9,
            'recipient' => self::RECIPIENT,
            'outcome' => 'SMTP_HOST_NOT_ALLOWED',
            'step' => 'send-time-check',
        ], $records[0]->context);
    }

    public function testNeverLogsTheSubjectBodyUsernameOrPassword(): void
    {
        $flow = $this->flow(static fn (): SmtpResult => new SmtpResult(new Reply(250, ['Ok'])));

        $flow->send($this->connection(), $this->message(), SendActor::user(5));

        foreach ([self::SUBJECT, self::BODY, self::USERNAME] as $secret) {
            foreach ($this->log->lines() as $line) {
                $this->assertStringNotContainsString($secret, $line);
            }
        }

        $this->log->assertNotLogged(self::PASSWORD);
    }

    public function testActorsGiveTheirLogContext(): void
    {
        $this->assertSame(['tokenId' => 3], SendActor::token(3)->logContext());
        $this->assertSame(['userId' => 4], SendActor::user(4)->logContext());
    }
}
