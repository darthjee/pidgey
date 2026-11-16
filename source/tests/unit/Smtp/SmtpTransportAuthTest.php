<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\SecretString;
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
use Pidgey\Smtp\Target;
use Pidgey\Tests\Support\SmtpTransportTestCase;

#[CoversClass(SmtpTransport::class)]
#[CoversClass(SmtpSessionState::class)]
#[UsesClass(Credentials::class)]
#[UsesClass(Deadline::class)]
#[UsesClass(EhloExtensions::class)]
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
#[UsesClass(Target::class)]
final class SmtpTransportAuthTest extends SmtpTransportTestCase
{
    public function testAuthPlainSendsTheInitialResponse(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH LOGIN PLAIN');
        $stream->expectLine('AUTH PLAIN ' . $this->plainPayload())->reply("235 2.7.0 ok\r\n");
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, credentials: $this->credentials());

        $stream->assertDone();
    }

    public function testAuthLoginAnswersBothPrompts(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH=LOGIN');
        $this->login($stream);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, credentials: $this->credentials());

        $stream->assertDone();
    }

    public function testRejectedPlainIsNotRetriedWithLogin(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN LOGIN');
        $stream->expectLine('AUTH PLAIN ' . $this->plainPayload())->reply("535 5.7.8 bad credentials\r\n");

        $failure = $this->sendFailure($stream, credentials: $this->credentials());

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Auth, $failure->step());
        $this->assertSame(535, $failure->reply()?->code());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
        $this->assertNoCanary($failure);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function loginSteps(): array
    {
        return ['AUTH LOGIN' => [0], 'username' => [1], 'password' => [2]];
    }

    #[DataProvider('loginSteps')]
    public function testLoginRejectedAtAnyStepIsAnAuthFailure(int $rejectedAt): void
    {
        $lines = ['AUTH LOGIN', base64_encode(self::USERNAME), base64_encode(self::CANARY)];
        $replies = ["334 VXNlcm5hbWU6\r\n", "334 UGFzc3dvcmQ6\r\n", "235 ok\r\n"];
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH LOGIN');

        for ($step = 0; $step <= $rejectedAt; $step++) {
            $stream->expectLine($lines[$step])->reply($step === $rejectedAt ? "535 5.7.8 no\r\n" : $replies[$step]);
        }

        $failure = $this->sendFailure($stream, credentials: $this->credentials());

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Auth, $failure->step());
        $this->assertSame(535, $failure->reply()?->code());
        $stream->assertDone();
        $this->assertNoCanary($failure);
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function unsupportedAuth(): array
    {
        return [
            'no AUTH' => [['SIZE 1000']],
            'CRAM-MD5 only' => [['AUTH CRAM-MD5 XOAUTH2']],
        ];
    }

    /**
     * @param list<string> $extensions
     */
    #[DataProvider('unsupportedAuth')]
    public function testNoSupportedMechanismNeverSendsTheCredentials(array $extensions): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, ...$extensions);
        $this->quit($stream);

        $failure = $this->sendFailure($stream, credentials: $this->credentials());

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Auth, $failure->step());
        $this->assertNull($failure->reply());
        $stream->assertDone();
        $this->assertNoCanary($failure);
    }

    public function testWithoutCredentialsNothingIsAuthenticated(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN LOGIN');
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream);

        $stream->assertDone();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function authReplies(): array
    {
        return [
            '454 temporary' => ["454 4.7.0 try later\r\n"],
            '534 weak' => ["534 5.7.9 weak mechanism\r\n"],
            '250 unexpected' => ["250 ok\r\n"],
        ];
    }

    #[DataProvider('authReplies')]
    public function testAnyReplyOtherThan235IsAnAuthFailure(string $raw): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN');
        $stream->expectLinePrefix('AUTH PLAIN ')->reply($raw);

        $failure = $this->sendFailure($stream, credentials: $this->credentials());

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame((int) substr($raw, 0, 3), $failure->reply()?->code());
        $this->assertNoCanary($failure);
    }

    public function testAuthTimeoutIsATimeout(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN');
        $stream->expectLinePrefix('AUTH PLAIN ')->reply("235 ok\r\n", 31.0);

        $failure = $this->sendFailure($stream, credentials: $this->credentials());

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::Auth, $failure->step());
        $this->assertNoCanary($failure);
    }

    public function testMailFrom530IsAnAuthFailure(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $stream->expectLine('MAIL FROM:<from@example.com>')->reply("530 5.7.0 Must issue a STARTTLS command first\r\n");

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertSame(530, $failure->reply()?->code());
        $stream->assertDone();
    }

    public function testMailFrom530AfterAuthenticationIsAnAuthFailure(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN');
        $stream->expectLinePrefix('AUTH PLAIN ')->reply("235 ok\r\n");
        $stream->expectLine('MAIL FROM:<from@example.com>')->reply("530 5.7.0 auth required\r\n");

        $failure = $this->sendFailure($stream, credentials: $this->credentials());

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertNoCanary($failure);
    }
}
