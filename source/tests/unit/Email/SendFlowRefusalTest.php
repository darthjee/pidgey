<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

use LogicException;
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
use Pidgey\Model\SmtpEncryption;
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
final class SendFlowRefusalTest extends SendFlowTestCase
{
    public function testRefusesInsecureCredentialsBeforeAnythingElse(): void
    {
        // An undecryptable password and a denied host prove the order: neither is reached.
        $connection = $this->connection(host: '127.0.0.1', encryption: SmtpEncryption::None, password: 'garbage');

        $outcome = $this->flow($this->ok())->send($connection, $this->message(), SendActor::user(5));

        $this->assertSendTimeFailure('SMTP_INSECURE_CREDENTIALS', $outcome);
    }

    public function testRefusesADeniedHostBeforeDecrypting(): void
    {
        $connection = $this->connection(host: '10.0.0.1', password: 'garbage');

        $outcome = $this->flow($this->ok())->send($connection, $this->message(), SendActor::user(5));

        $this->assertSendTimeFailure('SMTP_HOST_NOT_ALLOWED', $outcome);
    }

    public function testRefusesAHostResolvingToADeniedAddress(): void
    {
        $outcome = $this->flow($this->ok(), [self::PUBLIC_IP, '169.254.169.254'])
            ->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertSendTimeFailure('SMTP_HOST_NOT_ALLOWED', $outcome);
    }

    public function testAnUnresolvableHostIsAConnectionFailure(): void
    {
        $outcome = $this->flow($this->ok(), [])->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertFalse($outcome->isSent());
        $this->assertSame('SMTP_CONNECTION_FAILED', $outcome->failureCode());
        $this->assertSame('connect', $outcome->step());
        $this->assertNull($outcome->replyCode());
        $this->assertSame([], $this->calls);
    }

    public function testAnUnsafeMessageIsALogicErrorAndNeverReachesTheTransport(): void
    {
        $this->expectException(LogicException::class);

        try {
            $message = new Message("a@example.com\r\nBcc: x", 'Hi', 'x', null);

            $this->flow($this->ok())->send($this->connection(), $message, SendActor::user(5));
        } finally {
            $this->assertSame([], $this->calls);
        }
    }
}
