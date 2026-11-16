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
final class SmtpTransportFakeServerTest extends SmtpTransportIntegrationTestCase
{
    public function testMultiLineFinalReplyIsReturnedWhole(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250 fake\r\n"],
            ...$this->envelopeSteps(),
            ['expect' => '.', 'reply' => "250-2.0.0 accepted\r\n250 2.0.0 queued as 42\r\n"],
            ['expect' => 'QUIT', 'reply' => "221 bye\r\n"],
        ]);

        $result = $this->sendToServer(TlsMode::Plain);

        $this->assertSame(['2.0.0 accepted', '2.0.0 queued as 42'], $result->reply()->lines());
        $this->assertServerClean();
    }

    /**
     * @return array<string, array{list<array<string, mixed>>, Reply|null}>
     */
    public static function authFailures(): array
    {
        return [
            'CRAM-MD5 only' => [
                [
                    ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH CRAM-MD5\r\n"],
                    ['expect' => 'QUIT', 'reply' => "221 bye\r\n"],
                ],
                null,
            ],
            'PLAIN rejected, no LOGIN retry' => [
                [
                    ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH LOGIN PLAIN\r\n"],
                    ['expectPrefix' => 'AUTH PLAIN ', 'reply' => "535 5.7.8 bad credentials\r\n"],
                ],
                new Reply(535, ['5.7.8 bad credentials']),
            ],
            'LOGIN rejected at the password' => [
                [
                    ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH LOGIN\r\n"],
                    ['expect' => 'AUTH LOGIN', 'reply' => "334 VXNlcm5hbWU6\r\n"],
                    ['expect' => base64_encode('user'), 'reply' => "334 UGFzc3dvcmQ6\r\n"],
                    ['expect' => base64_encode(self::CANARY), 'reply' => "535 5.7.8 nope\r\n"],
                ],
                new Reply(535, ['5.7.8 nope']),
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $steps
     */
    #[DataProvider('authFailures')]
    public function testAuthFailuresOnTheFakeServer(array $steps, ?Reply $reply): void
    {
        $this->startServer([['reply' => "220 fake ready\r\n"], ...$steps]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Plain, $this->canaryCredentials()));

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Auth, $failure->step());
        $this->assertEquals($reply, $failure->reply());
        $this->assertNoCanary($failure);
        $this->assertServerClean();
    }

    public function testMalformedReplyIsAProtocolError(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n251 mixed codes\r\n"],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Plain));

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::Ehlo, $failure->step());
        $this->assertInstanceOf(MalformedReply::class, $failure->getPrevious());
        $this->assertServerClean();
    }

    public function testTemporaryFailureAtRcptTo(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250 fake\r\n"],
            ['expect' => 'MAIL FROM:<' . self::FROM . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'RCPT TO:<' . self::TO . '>', 'reply' => "451 4.3.0 try again later\r\n"],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Plain));

        $this->assertSame(FailureCode::TemporaryFailure, $failure->failureCode());
        $this->assertSame(SmtpStep::RcptTo, $failure->step());
        $this->assertSame(451, $failure->reply()?->code());
        $this->assertServerClean();
    }

    public function testRejectedMessageAtEndOfData(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250 fake\r\n"],
            ...$this->envelopeSteps(),
            ['expect' => '.', 'reply' => "554 5.6.0 message refused\r\n"],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Plain));

        $this->assertSame(FailureCode::Rejected, $failure->failureCode());
        $this->assertSame(SmtpStep::EndOfData, $failure->step());
        $this->assertServerClean();
    }
}
