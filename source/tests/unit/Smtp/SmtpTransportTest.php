<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Smtp;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
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
use Pidgey\Smtp\TlsMode;
use Pidgey\Tests\Support\FakeSmtpStream;
use Pidgey\Tests\Support\FrozenClock;
use RuntimeException;
use Throwable;

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
final class SmtpTransportTest extends TestCase
{
    private const CANARY = 'canary-unit-marker-7c2d';
    private const USERNAME = 'mailer@example.com';
    private const HOST = 'smtp.example.com';
    private const CA_FILE = '/etc/pidgey/ca.pem';
    private const EHLO = 'EHLO [192.0.2.10]';
    private const MESSAGE = "Subject: hi\r\n\r\nbody\r\n";

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(1000.0);
    }

    public function testDeliversAMessageAndReturnsTheFinalReply(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $result = $this->send($stream);

        $this->assertSame(250, $result->code());
        $this->assertSame(['2.0.0 queued as ABC'], $result->reply()->lines());
        $this->assertSame(
            [['address' => '192.0.2.1', 'port' => 587, 'peerName' => self::HOST, 'implicitTls' => false,
                'caFile' => self::CA_FILE]],
            $stream->opens(),
        );
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    public function testDefaultStreamFactoryBuildsASocketStream(): void
    {
        $transport = new SmtpTransport($this->clock);
        $settings = new SessionSettings(1.0, 1.0, 0.0, null, null);

        $failure = $this->failure(fn () => $transport->send(
            new Target(self::HOST, '192.0.2.1', 25, TlsMode::Plain),
            $settings,
            null,
            $this->envelope(),
            self::MESSAGE,
        ));

        $this->assertSame(FailureCode::ConnectionFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Connect, $failure->step());
    }

    public function testStreamFactoryIsCalledOncePerSend(): void
    {
        $calls = 0;
        $streams = [$this->happyStream(), $this->happyStream()];
        $transport = new SmtpTransport($this->clock, static function () use (&$calls, $streams): FakeSmtpStream {
            return $streams[$calls++];
        });

        $transport->send($this->target(), $this->settings(), null, $this->envelope(), self::MESSAGE);
        $transport->send($this->target(), $this->settings(), null, $this->envelope(), self::MESSAGE);

        $this->assertSame(2, $calls);
    }

    // Greeting.

    /**
     * @return array<string, array{string, FailureCode}>
     */
    public static function greetings(): array
    {
        return [
            '554 rejected' => ["554 5.7.1 no thanks\r\n", FailureCode::Rejected],
            '421 busy' => ["421 4.3.2 busy\r\n", FailureCode::TemporaryFailure],
            '450 other 4xx' => ["450 later\r\n", FailureCode::TemporaryFailure],
            '550 other 5xx' => ["550 go away\r\n", FailureCode::Rejected],
            '250 not an error' => ["250 hello\r\n", FailureCode::ProtocolError],
        ];
    }

    #[DataProvider('greetings')]
    public function testGreetingOtherThan220Fails(string $raw, FailureCode $code): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->reply($raw);

        $failure = $this->sendFailure($stream);

        $this->assertSame($code, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
        $this->assertSame((int) substr($raw, 0, 3), $failure->reply()?->code());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    public function testCloseBeforeTheGreetingIsAConnectionFailure(): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->failRead(StreamFailureKind::Closed);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::ConnectionFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
        $this->assertNull($failure->reply());
        $this->assertInstanceOf(StreamFailure::class, $failure->getPrevious());
        $this->assertTrue($stream->isClosed());
    }

    public function testGreetingTimeoutIsATimeout(): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->reply("220 late\r\n", 10.5);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
        $this->assertSame(1010.0, $this->clock->now());
    }

    public function testOpenAndGreetingShareTheConnectTimeout(): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->reply("220 a\r\n", 6.0);

        $settings = new SessionSettings(5.0, 30.0, 120.0, self::CA_FILE, null);

        $this->assertSame(FailureCode::Timeout, $this->sendFailure($stream, settings: $settings)->failureCode());
        $this->assertSame(1005.0, $this->clock->now());
    }

    /**
     * @return array<string, array{StreamFailureKind, TlsMode, FailureCode}>
     */
    public static function openFailures(): array
    {
        return [
            'connect' => [StreamFailureKind::Connect, TlsMode::Plain, FailureCode::ConnectionFailed],
            'implicit TLS handshake' => [StreamFailureKind::Tls, TlsMode::Implicit, FailureCode::TlsFailed],
            'implicit TLS timeout' => [StreamFailureKind::Timeout, TlsMode::Implicit, FailureCode::Timeout],
            'plain connect timeout' => [StreamFailureKind::Timeout, TlsMode::Plain, FailureCode::ConnectionFailed],
        ];
    }

    #[DataProvider('openFailures')]
    public function testOpenFailureIsClassified(StreamFailureKind $kind, TlsMode $mode, FailureCode $code): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->fail($kind);

        $failure = $this->sendFailure($stream, $this->target($mode));

        $this->assertSame($code, $failure->failureCode());
        $this->assertSame(SmtpStep::Connect, $failure->step());
        $this->assertNull($failure->reply());
        $this->assertSame($kind === StreamFailureKind::Tls ? 'fake TLS failure' : null, $failure->reason());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    public function testExpiredSessionDeadlineFailsWithoutIo(): void
    {
        $stream = $this->stream();
        $stream->expectOpen();

        $settings = new SessionSettings(10.0, 30.0, 0.0, self::CA_FILE, null);
        $failure = $this->sendFailure($stream, settings: $settings);

        $this->assertSame(FailureCode::ConnectionFailed, $failure->failureCode());
        $this->assertSame([], $stream->opens());
        $this->assertTrue($stream->isClosed());
    }

    // EHLO.

    public function testEhloUsesTheConfiguredName(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->expectLine('EHLO mta.pidgey.test')->reply("250 mx\r\n");
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, settings: new SessionSettings(10.0, 30.0, 120.0, null, 'mta.pidgey.test'));

        $stream->assertDone();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function localAddresses(): array
    {
        return [
            'IPv4' => ['203.0.113.7', 'EHLO [203.0.113.7]'],
            'IPv6' => ['2001:db8::7', 'EHLO [IPv6:2001:db8::7]'],
        ];
    }

    #[DataProvider('localAddresses')]
    public function testEhloFallsBackToTheLocalAddressLiteral(string $local, string $line): void
    {
        $stream = $this->stream($local);
        $this->greet($stream);
        $stream->expectLine($line)->reply("250 mx\r\n");
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, settings: new SessionSettings(10.0, 30.0, 120.0, null, ''));

        $stream->assertDone();
    }

    /**
     * @return array<string, array{string, FailureCode}>
     */
    public static function ehloRejections(): array
    {
        return [
            '550' => ["550 5.7.1 go away\r\n", FailureCode::Rejected],
            '421' => ["421 4.3.2 later\r\n", FailureCode::TemporaryFailure],
            '502 unknown command' => ["502 5.5.2 what\r\n", FailureCode::Rejected],
        ];
    }

    #[DataProvider('ehloRejections')]
    public function testEhloRejectionHasNoHeloFallback(string $raw, FailureCode $code): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->expectLine(self::EHLO)->reply($raw);

        $failure = $this->sendFailure($stream);

        $this->assertSame($code, $failure->failureCode());
        $this->assertSame(SmtpStep::Ehlo, $failure->step());
        $this->assertNotNull($failure->reply());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    // STARTTLS.

    public function testStartTlsUpgradesThenSendsEhloAgain(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS');
        $stream->expectLine('STARTTLS')->reply("220 2.0.0 go ahead\r\n")->expectUpgrade();
        $this->ehlo($stream);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, $this->target(TlsMode::StartTls));

        $this->assertSame([['peerName' => self::HOST, 'caFile' => self::CA_FILE]], $stream->upgrades());
        $this->assertFalse($stream->opens()[0]['implicitTls']);
        $stream->assertDone();
    }

    public function testStartTlsNotAdvertisedFailsAndQuits(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN');
        $this->quit($stream);

        $failure = $this->sendFailure($stream, $this->target(TlsMode::StartTls), $this->credentials());

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertNull($failure->reply());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
        $this->assertNoCanary($failure);
    }

    public function testStartTlsNotAdvertisedFailsEvenWhenQuitFails(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $stream->expectLine('QUIT')->fail(StreamFailureKind::Closed);

        $failure = $this->sendFailure($stream, $this->target(TlsMode::StartTls));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $stream->assertDone();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function startTlsRefusals(): array
    {
        return [
            '454' => ["454 4.7.0 TLS not available\r\n"],
            '502' => ["502 5.5.1 not implemented\r\n"],
            '250' => ["250 ok\r\n"],
        ];
    }

    #[DataProvider('startTlsRefusals')]
    public function testStartTlsRefusedIsATlsFailureWithTheReply(string $raw): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS');
        $stream->expectLine('STARTTLS')->reply($raw);

        $failure = $this->sendFailure($stream, $this->target(TlsMode::StartTls));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertSame((int) substr($raw, 0, 3), $failure->reply()?->code());
        $this->assertSame([], $stream->upgrades());
        $stream->assertDone();
    }

    /**
     * @return array<string, array{StreamFailureKind, FailureCode, string|null}>
     */
    public static function upgradeFailures(): array
    {
        return [
            'handshake' => [StreamFailureKind::Tls, FailureCode::TlsFailed, 'fake TLS failure'],
            'injected bytes' => [StreamFailureKind::UnexpectedData, FailureCode::TlsFailed, null],
            'handshake timeout' => [StreamFailureKind::Timeout, FailureCode::Timeout, null],
        ];
    }

    #[DataProvider('upgradeFailures')]
    public function testUpgradeFailureClosesWithoutQuit(
        StreamFailureKind $kind,
        FailureCode $code,
        ?string $reason,
    ): void {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS');
        $stream->expectLine('STARTTLS')->reply("220 go ahead\r\n")->expectUpgrade()->fail($kind);

        $failure = $this->sendFailure($stream, $this->target(TlsMode::StartTls));

        $this->assertSame($code, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertNull($failure->reply());
        $this->assertSame($reason, $failure->reason());
        $this->assertStringNotContainsString('fake TLS failure', $failure->getMessage());
        $stream->assertDone();
        $this->assertTrue($stream->isClosed());
    }

    public function testPostTlsExtensionsReplaceThePreTlsList(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS', 'AUTH PLAIN');
        $stream->expectLine('STARTTLS')->reply("220 go ahead\r\n")->expectUpgrade();
        $this->ehlo($stream, 'AUTH LOGIN');
        $this->login($stream);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, $this->target(TlsMode::StartTls), $this->credentials());

        $stream->assertDone();
    }

    public function testPostTlsEhloRejectionIsClassifiedAtEhlo(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS');
        $stream->expectLine('STARTTLS')->reply("220 go ahead\r\n")->expectUpgrade();
        $stream->expectLine(self::EHLO)->reply("554 no\r\n");

        $failure = $this->sendFailure($stream, $this->target(TlsMode::StartTls));

        $this->assertSame(FailureCode::Rejected, $failure->failureCode());
        $this->assertSame(SmtpStep::Ehlo, $failure->step());
    }

    public function testImplicitTlsHandshakesAtOpenAndSendsNoStartTls(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'STARTTLS');
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream, $this->target(TlsMode::Implicit));

        $this->assertTrue($stream->opens()[0]['implicitTls']);
        $this->assertSame(self::HOST, $stream->opens()[0]['peerName']);
        $this->assertSame([], $stream->upgrades());
        $stream->assertDone();
    }

    // AUTH.

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

    public function testAuthWriteFailureLeaksNoSecret(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN');
        $stream->fail(StreamFailureKind::Closed);

        $failure = $this->sendFailure($stream, credentials: $this->credentials());

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::Auth, $failure->step());
        $this->assertNoCanary($failure);
    }

    // Envelope and DATA.

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

    // Replies.

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
     * @return array<string, array{string}>
     */
    public static function malformedReplies(): array
    {
        return [
            'garbage' => ["hello\r\n"],
            'mixed codes' => ["250-a\r\n251 b\r\n"],
            'code out of range' => ["199 x\r\n"],
            'close mid-reply' => ["250-a\r\n"],
            'close mid-line' => ['250 no line end'],
        ];
    }

    #[DataProvider('malformedReplies')]
    public function testMalformedReplyIsAProtocolError(string $raw): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $stream->expectLine('MAIL FROM:<from@example.com>')->reply($raw);

        if (str_ends_with($raw, "-a\r\n")) {
            $stream->failRead(StreamFailureKind::Closed);
        }

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertNull($failure->reply());
        $this->assertInstanceOf(MalformedReply::class, $failure->getPrevious());
        $this->assertTrue($stream->isClosed());
    }

    public function testMalformedGreetingIsAProtocolError(): void
    {
        $stream = $this->stream();
        $stream->expectOpen()->reply("SSH-2.0-OpenSSH\r\n");

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
    }

    public function testCloseWithNoReplyAfterTheGreetingIsAProtocolError(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $stream->expectLine('MAIL FROM:<from@example.com>')->reply("250 ok\r\n");
        $stream->expectLine('RCPT TO:<to@example.com>')->fail(StreamFailureKind::Closed);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::RcptTo, $failure->step());
        $this->assertNull($failure->reply());
        $stream->assertDone();
    }

    public function testWriteToAClosedPeerIsAProtocolError(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->fail(StreamFailureKind::Closed);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::ProtocolError, $failure->failureCode());
        $this->assertSame(SmtpStep::Ehlo, $failure->step());
    }

    // Timeouts.

    public function testReplyBeyondTheCommandTimeoutIsATimeout(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $stream->expectLine('MAIL FROM:<from@example.com>')->reply("250 ok\r\n", 30.5);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertSame(1030.0, $this->clock->now());
    }

    public function testReplyJustWithinTheCommandTimeoutSucceeds(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->expectLine(self::EHLO)->reply("250 mx\r\n", 30.0);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $this->send($stream);

        $stream->assertDone();
    }

    public function testSessionDeadlineCutsALaterReplyShort(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->expectLine(self::EHLO)->reply("250 mx\r\n", 25.0);
        $stream->expectLine('MAIL FROM:<from@example.com>')->reply("250 ok\r\n", 26.0);

        $settings = new SessionSettings(10.0, 30.0, 50.0, self::CA_FILE, null);
        $failure = $this->sendFailure($stream, settings: $settings);

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertSame(1050.0, $this->clock->now());
    }

    public function testExpiredSessionDeadlineFailsTheNextWriteWithoutIo(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $stream->expectLine(self::EHLO)->reply("250 mx\r\n", 20.0);
        $stream->expectLine('MAIL FROM:<from@example.com>');

        $settings = new SessionSettings(10.0, 30.0, 20.0, self::CA_FILE, null);
        $failure = $this->sendFailure($stream, settings: $settings);

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertSame(1020.0, $this->clock->now());
        $this->assertTrue($stream->isClosed());
    }

    public function testFinalReplyTimeoutIsATimeoutAtEndOfData(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $this->envelopeAndData($stream, ['Subject: hi', '', 'body'], "250 late\r\n", 31.0);

        $failure = $this->sendFailure($stream);

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::EndOfData, $failure->step());
    }

    // QUIT.

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

    // Canary.

    public function testSuccessWithCredentialsLeaksNoSecret(): void
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream, 'AUTH PLAIN');
        $stream->expectLine('AUTH PLAIN ' . $this->plainPayload())->reply("235 ok\r\n");
        $this->envelopeAndData($stream);
        $this->quit($stream);

        $result = $this->send($stream, credentials: $this->credentials());

        $this->assertStringNotContainsString(self::CANARY, print_r($result, true));
    }

    public function testCanaryCheckDetectsALeak(): void
    {
        $previous = new RuntimeException(self::CANARY);
        $leak = new SmtpFailure(FailureCode::AuthFailed, SmtpStep::Auth, null, null, $previous);

        $this->assertNotEmpty($this->leaks($leak));
    }

    private function stream(string $localAddress = '192.0.2.10'): FakeSmtpStream
    {
        return new FakeSmtpStream($this->clock, $localAddress);
    }

    private function happyStream(): FakeSmtpStream
    {
        $stream = $this->stream();
        $this->greet($stream);
        $this->ehlo($stream);
        $this->envelopeAndData($stream);
        $this->quit($stream);

        return $stream;
    }

    private function greet(FakeSmtpStream $stream): void
    {
        $stream->expectOpen()->reply("220 mx.example.com ESMTP\r\n");
    }

    private function ehlo(FakeSmtpStream $stream, string ...$extensions): void
    {
        $lines = ['mx.example.com', ...$extensions];
        $raw = '';

        foreach ($lines as $index => $line) {
            $raw .= '250' . ($index === count($lines) - 1 ? ' ' : '-') . $line . "\r\n";
        }

        $stream->expectLine(self::EHLO)->reply($raw);
    }

    private function login(FakeSmtpStream $stream): void
    {
        $stream
            ->expectLine('AUTH LOGIN')->reply("334 VXNlcm5hbWU6\r\n")
            ->expectLine(base64_encode(self::USERNAME))->reply("334 UGFzc3dvcmQ6\r\n")
            ->expectLine(base64_encode(self::CANARY))->reply("235 2.7.0 ok\r\n");
    }

    /**
     * @param list<string> $lines
     */
    private function envelopeAndData(
        FakeSmtpStream $stream,
        array $lines = ['Subject: hi', '', 'body'],
        string $final = "250 2.0.0 queued as ABC\r\n",
        float $finalDelay = 0.0,
    ): void {
        $stream
            ->expectLine('MAIL FROM:<from@example.com>')->reply("250 2.1.0 ok\r\n")
            ->expectLine('RCPT TO:<to@example.com>')->reply("250 2.1.5 ok\r\n")
            ->expectLine('DATA')->reply("354 go ahead\r\n");

        foreach ($lines as $line) {
            $stream->expectLine($line);
        }

        $stream->expectLine('.')->reply($final, $finalDelay);
    }

    /**
     * Scripts the envelope up to the step, which gets the raw reply.
     */
    private function upTo(FakeSmtpStream $stream, SmtpStep $step, string $raw): void
    {
        $steps = [
            [SmtpStep::MailFrom, 'MAIL FROM:<from@example.com>', "250 ok\r\n"],
            [SmtpStep::RcptTo, 'RCPT TO:<to@example.com>', "250 ok\r\n"],
            [SmtpStep::Data, 'DATA', "354 go\r\n"],
        ];

        foreach ($steps as [$current, $line, $reply]) {
            $stream->expectLine($line)->reply($current === $step ? $raw : $reply);

            if ($current === $step) {
                return;
            }
        }

        $stream->expectLine('Subject: hi')->expectLine('')->expectLine('body')->expectLine('.')->reply($raw);
    }

    private function quit(FakeSmtpStream $stream): void
    {
        $stream->expectLine('QUIT')->reply("221 2.0.0 bye\r\n");
    }

    private function target(TlsMode $mode = TlsMode::Plain): Target
    {
        return new Target(self::HOST, '192.0.2.1', 587, $mode);
    }

    private function settings(): SessionSettings
    {
        return new SessionSettings(10.0, 30.0, 120.0, self::CA_FILE, null);
    }

    private function envelope(): Envelope
    {
        return new Envelope('from@example.com', ['to@example.com']);
    }

    private function credentials(): Credentials
    {
        return new Credentials(self::USERNAME, new SecretString(self::CANARY));
    }

    private function plainPayload(): string
    {
        return base64_encode("\0" . self::USERNAME . "\0" . self::CANARY);
    }

    private function send(
        FakeSmtpStream $stream,
        ?Target $target = null,
        ?Credentials $credentials = null,
        ?SessionSettings $settings = null,
        ?Envelope $envelope = null,
        string $message = self::MESSAGE,
    ): SmtpResult {
        $transport = new SmtpTransport($this->clock, static fn (): FakeSmtpStream => $stream);

        return $transport->send(
            $target ?? $this->target(),
            $settings ?? $this->settings(),
            $credentials,
            $envelope ?? $this->envelope(),
            $message,
        );
    }

    private function sendFailure(
        FakeSmtpStream $stream,
        ?Target $target = null,
        ?Credentials $credentials = null,
        ?SessionSettings $settings = null,
    ): SmtpFailure {
        return $this->failure(fn () => $this->send($stream, $target, $credentials, $settings));
    }

    private function failure(Closure $call): SmtpFailure
    {
        try {
            $call();
        } catch (SmtpFailure $failure) {
            return $failure;
        }

        $this->fail('expected an SmtpFailure');
    }

    private function assertNoCanary(Throwable $failure): void
    {
        $this->assertSame([], $this->leaks($failure), 'a secret leaked into an exception message');
    }

    /**
     * Returns the secret forms found in the messages of the exception chain.
     *
     * @return list<string>
     */
    private function leaks(Throwable $failure): array
    {
        $secrets = [
            self::CANARY,
            base64_encode(self::CANARY),
            $this->plainPayload(),
            'AUTH PLAIN ' . $this->plainPayload(),
        ];
        $found = [];

        for ($current = $failure; $current !== null; $current = $current->getPrevious()) {
            foreach ($secrets as $secret) {
                if (str_contains($current->getMessage(), $secret)) {
                    $found[] = $secret;
                }
            }
        }

        return $found;
    }
}
