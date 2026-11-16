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
use Pidgey\Smtp\StreamFailureKind;
use Pidgey\Smtp\Target;
use Pidgey\Tests\Support\FakeSmtpStream;
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
final class SmtpTransportEnvelopeTest extends SmtpTransportTestCase
{
    /**
     * @return array<string, array{SmtpStep, string, FailureCode}>
     */
    public static function envelopeRejections(): array
    {
        $cases = [];

        foreach ([SmtpStep::MailFrom, SmtpStep::RcptTo, SmtpStep::Data, SmtpStep::EndOfData] as $step) {
            $cases["{$step->name} 550"] = [$step, "550 5.1.1 no such user\r\n", FailureCode::Rejected];
            $cases["{$step->name} 451"] = [$step, "451 4.3.0 try later\r\n", FailureCode::TemporaryFailure];
        }

        $cases['RcptTo 530'] = [SmtpStep::RcptTo, "530 5.7.0 auth\r\n", FailureCode::Rejected];
        $cases['Data 250'] = [SmtpStep::Data, "250 ok\r\n", FailureCode::ProtocolError];
        $cases['EndOfData 354'] = [SmtpStep::EndOfData, "354 more\r\n", FailureCode::ProtocolError];

        return $cases;
    }

    #[DataProvider('envelopeRejections')]
    public function testEnvelopeAndDataRejectionsAreClassified(SmtpStep $step, string $raw, FailureCode $code): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $this->upTo($stream, $step, $raw);

        $failure = $this->sendFailure($stream);

        $this->assertSame($code, $failure->failureCode());
        $this->assertSame($step, $failure->step());
        $this->assertSame((int) substr($raw, 0, 3), $failure->reply()?->code());
        $this->assertStringNotContainsString('no such user', $failure->getMessage());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    public function testEveryRecipientGetsItsRcptTo(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $stream
            ->expectLine('MAIL FROM:<from@example.com>')->reply("250 ok\r\n")
            ->expectLine('RCPT TO:<a@example.com>')->reply("250 ok\r\n")
            ->expectLine('RCPT TO:<b@example.com>')->reply("251 forwarded\r\n")
            ->expectLine('DATA')->reply("354 go\r\n")
            ->expectLine('x')
            ->expectLine('.')->reply("250 queued\r\n");
        $this->quit($stream);

        $envelope = new Envelope('from@example.com', ['a@example.com', 'b@example.com']);

        $this->send($stream, envelope: $envelope, message: 'x');

        $stream->assertDone();
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function messages(): array
    {
        return [
            'dot-stuffing' => [".x\r\n.\r\nok\r\n", ['..x', '..', 'ok']],
            'bare LF' => ["a\nb\n", ['a', 'b']],
            'bare CR' => ["a\rb", ['a', 'b']],
            'mixed endings' => ["a\r\n\r\nb\rc\nd", ['a', '', 'b', 'c', 'd']],
            'smuggled end of data' => ["a\n.\nb", ['a', '..', 'b']],
            'smuggled CR end of data' => ["a\r.\rb\r\n", ['a', '..', 'b']],
            'no final line break' => ['body', ['body']],
            'blank lines kept' => ["a\r\n\r\n\r\n", ['a', '', '']],
        ];
    }

    /**
     * @param list<string> $lines
     */
    #[DataProvider('messages')]
    public function testMessageIsNormalizedAndDotStuffed(string $message, array $lines): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $this->envelopeAndData($stream, $lines);
        $this->quit($stream);

        $this->send($stream, message: $message);

        $stream->assertDone();
    }

    public function testMultiLineFinalReplyIsReturnedWhole(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $final = "250-2.0.0 accepted\r\n250-2.0.0 queued\r\n250 2.0.0 id=42\r\n";
        $this->envelopeAndData($stream, ['Subject: hi', '', 'body'], $final);
        $this->quit($stream);

        $result = $this->send($stream);

        $this->assertSame(['2.0.0 accepted', '2.0.0 queued', '2.0.0 id=42'], $result->reply()->lines());
    }

    /**
     * @return array<string, array{Closure(FakeSmtpStream): void}>
     */
    public static function quitOutcomes(): array
    {
        return [
            '500 reply' => [static fn (FakeSmtpStream $stream) => $stream->expectLine('QUIT')->reply("500 what\r\n")],
            'closed on write' => [static fn (FakeSmtpStream $stream) => $stream->fail(StreamFailureKind::Closed)],
            'closed on read' => [
                static fn (FakeSmtpStream $stream) => $stream->expectLine('QUIT')->fail(StreamFailureKind::Closed),
            ],
            'timeout' => [
                static fn (FakeSmtpStream $stream) => $stream->expectLine('QUIT')->reply("221 bye\r\n", 31.0),
            ],
            'malformed' => [static fn (FakeSmtpStream $stream) => $stream->expectLine('QUIT')->reply("bye\r\n")],
        ];
    }

    /**
     * @param Closure(FakeSmtpStream): void $quit
     */
    #[DataProvider('quitOutcomes')]
    public function testQuitNeverTurnsASentMessageIntoAFailure(Closure $quit): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $this->envelopeAndData($stream);
        $quit($stream);

        $result = $this->send($stream);

        $this->assertSame(250, $result->code());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }
}
