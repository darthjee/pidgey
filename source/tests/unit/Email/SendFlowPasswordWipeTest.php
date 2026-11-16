<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

use LogicException;
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
final class SendFlowPasswordWipeTest extends SendFlowTestCase
{
    public function testWipesThePasswordAfterASuccess(): void
    {
        $this->flow($this->ok())->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertWiped();
    }

    public function testWipesThePasswordAfterAFailure(): void
    {
        $flow = $this->flow(static fn (): never => throw new SmtpFailure(FailureCode::Timeout, SmtpStep::Data));

        $flow->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertWiped();
    }

    public function testWipesThePasswordWhenAnUnexpectedErrorPropagates(): void
    {
        $flow = $this->flow(static fn (): never => throw new LogicException('boom'));

        try {
            $flow->send($this->connection(), $this->message(), SendActor::user(5));
            $this->fail('the error should propagate');
        } catch (LogicException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertWiped();
        $this->assertSame([], $this->log->records());
    }
}
