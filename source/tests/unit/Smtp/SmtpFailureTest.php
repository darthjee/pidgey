<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Smtp\FailureCode;
use Pidgey\Smtp\MalformedReply;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpStep;

#[CoversClass(SmtpFailure::class)]
#[UsesClass(Reply::class)]
#[UsesClass(MalformedReply::class)]
final class SmtpFailureTest extends TestCase
{
    public function testMessageIsTheStepAndTheCode(): void
    {
        $failure = new SmtpFailure(FailureCode::Rejected, SmtpStep::RcptTo);

        $this->assertSame('SMTP RcptTo: SMTP_REJECTED', $failure->getMessage());
    }

    public function testMessageNeverContainsTheReplyText(): void
    {
        $reply = new Reply(550, ['5.1.1 <secret-user@example.com> unknown user']);

        $failure = new SmtpFailure(FailureCode::Rejected, SmtpStep::RcptTo, $reply);

        $this->assertStringNotContainsString('secret-user', $failure->getMessage());
        $this->assertStringNotContainsString('550', $failure->getMessage());
    }

    public function testAccessorsReturnWhatWasGiven(): void
    {
        $reply = new Reply(535, ['5.7.8 nope']);
        $previous = new MalformedReply();

        $failure = new SmtpFailure(FailureCode::AuthFailed, SmtpStep::Auth, $reply, null, $previous);

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Auth, $failure->step());
        $this->assertSame($reply, $failure->reply());
        $this->assertNull($failure->reason());
        $this->assertSame($previous, $failure->getPrevious());
    }

    public function testReplyDefaultsToNull(): void
    {
        $this->assertNull((new SmtpFailure(FailureCode::Timeout, SmtpStep::Data))->reply());
    }

    public function testReasonIsKeptForTlsFailures(): void
    {
        $failure = new SmtpFailure(FailureCode::TlsFailed, SmtpStep::StartTls, null, 'certificate verify failed');

        $this->assertSame('certificate verify failed', $failure->reason());
        $this->assertStringNotContainsString('certificate', $failure->getMessage());
    }

    public function testReasonIsDroppedForOtherFailures(): void
    {
        $failure = new SmtpFailure(FailureCode::ConnectionFailed, SmtpStep::Connect, null, 'some reason');

        $this->assertNull($failure->reason());
    }
}
