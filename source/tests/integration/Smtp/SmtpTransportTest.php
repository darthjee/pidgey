<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Smtp;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
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
use Pidgey\Tests\Support\FakeSmtpServer;
use Pidgey\Tests\Support\Mailpit;
use Throwable;

/**
 * End-to-end tests of the SMTP session over real sockets: the Mailpit instances for delivery
 * and authentication, `FakeSmtpServer` for what Mailpit cannot reproduce.
 *
 * TLS verification is never disabled: the test CA comes from `SMTP_CA_FILE` (Mailpit) or the
 * fixture bundle (`FakeSmtpServer`).
 */
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
final class SmtpTransportTest extends TestCase
{
    /**
     * Every transport failure code and the test of this file that reaches it.
     */
    private const COVERAGE = [
        'SMTP_CONNECTION_FAILED' => 'testCloseBeforeTheGreetingIsAConnectionFailure',
        'SMTP_TLS_FAILED' => 'testStartTlsNotAdvertisedIsATlsFailure',
        'SMTP_AUTH_FAILED' => 'testMailpitWrongPasswordIsAnAuthFailure',
        'SMTP_REJECTED' => 'testRejectedMessageAtEndOfData',
        'SMTP_TEMPORARY_FAILURE' => 'testTemporaryFailureAtRcptTo',
        'SMTP_PROTOCOL_ERROR' => 'testMalformedReplyIsAProtocolError',
        'SMTP_TIMEOUT' => 'testHangingGreetingIsATimeout',
    ];

    private const CANARY = 'canary-integration-marker-9e4a';
    private const FIXTURE_CA = __DIR__ . '/../../fixtures/tls/ca.pem';
    private const EHLO_NAME = 'client.example';
    private const TIMEOUT = 5.0;
    private const SHORT_TIMEOUT = 0.5;
    private const FROM = 'sender@pidgey.test';
    private const TO = 'recipient@pidgey.test';
    private const DOT_LINE = '.leading dot';

    private SmtpTransport $transport;

    private ?FakeSmtpServer $server = null;

    protected function setUp(): void
    {
        $this->transport = new SmtpTransport(new SystemClock());

        foreach ([Mailpit::plain(), Mailpit::starttls(), Mailpit::ssl()] as $mailpit) {
            $mailpit->clear();
        }
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    // Coverage matrix.

    /**
     * @return array<string, array{FailureCode}>
     */
    public static function failureCodes(): array
    {
        $cases = [];

        foreach (FailureCode::cases() as $code) {
            $cases[$code->value] = [$code];
        }

        return $cases;
    }

    #[DataProvider('failureCodes')]
    public function testEveryFailureCodeIsReachedByATestOfThisFile(FailureCode $code): void
    {
        $this->assertArrayHasKey($code->value, self::COVERAGE);
        $this->assertTrue(method_exists($this, self::COVERAGE[$code->value]));
    }

    // Mailpit: delivery.

    /**
     * @return array<string, array{string, TlsMode, bool}>
     */
    public static function mailpitDeliveries(): array
    {
        return [
            'plain, no credentials' => ['plain', TlsMode::Plain, false],
            'STARTTLS with credentials' => ['starttls', TlsMode::StartTls, true],
            'implicit TLS with credentials' => ['ssl', TlsMode::Implicit, true],
        ];
    }

    #[DataProvider('mailpitDeliveries')]
    public function testMailpitDeliversTheMessage(string $instance, TlsMode $mode, bool $authenticate): void
    {
        $mailpit = Mailpit::$instance();
        $subject = "transport {$instance}";
        $credentials = $authenticate
            ? new Credentials(Mailpit::AUTH_USER, new SecretString(Mailpit::AUTH_PASSWORD))
            : null;

        $result = $this->sendTo($mailpit, $mode, $credentials, $this->message($subject));

        $this->assertSame(250, $result->code());
        $messages = $mailpit->messages();
        $this->assertCount(1, $messages);
        $this->assertSame($subject, $messages[0]['Subject'] ?? null);

        $text = (string) ($mailpit->message((string) $messages[0]['ID'])['Text'] ?? '');
        $lines = preg_split('/\r\n|\n/', $text) ?: [];
        $this->assertContains(self::DOT_LINE, $lines);
        $this->assertContains('.', $lines);
        $this->assertNotContains('..', $lines);
    }

    // Mailpit: authentication.

    public function testMailpitWrongPasswordIsAnAuthFailure(): void
    {
        $credentials = new Credentials(Mailpit::AUTH_USER, new SecretString(self::CANARY));

        $failure = $this->failure(fn () => $this->sendTo(Mailpit::starttls(), TlsMode::StartTls, $credentials));

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Auth, $failure->step());
        $this->assertNotNull($failure->reply());
        $this->assertNoCanary($failure, Mailpit::AUTH_USER);
        $this->assertSame([], Mailpit::starttls()->messages());
    }

    public function testMailpitWithoutCredentialsIsAnAuthFailureAtMailFrom(): void
    {
        $failure = $this->failure(fn () => $this->sendTo(Mailpit::starttls(), TlsMode::StartTls, null));

        $this->assertSame(FailureCode::AuthFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::MailFrom, $failure->step());
        $this->assertSame(530, $failure->reply()?->code());
    }

    // Mailpit: port / encryption mismatch.

    public function testImplicitTlsAgainstThePlainPortIsATlsFailure(): void
    {
        $failure = $this->failure(fn () => $this->sendTo(Mailpit::plain(), TlsMode::Implicit, null));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Connect, $failure->step());
        $this->assertNotNull($failure->reason());
    }

    public function testPlainAgainstTheSslPortIsATimeout(): void
    {
        $settings = $this->mailpitSettings(self::SHORT_TIMEOUT);

        $failure = $this->failure(fn () => $this->sendTo(Mailpit::ssl(), TlsMode::Plain, null, settings: $settings));

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
    }

    // FakeSmtpServer.

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

    public function testStartTlsNotAdvertisedIsATlsFailure(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH PLAIN LOGIN\r\n"],
            ['expect' => 'QUIT', 'reply' => "221 bye\r\n"],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::StartTls, $this->canaryCredentials()));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertNull($failure->reply());
        $this->assertNoCanary($failure);
        $this->assertServerClean();
    }

    public function testInjectedBytesBeforeTheHandshakeAreATlsFailure(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 STARTTLS\r\n"],
            [
                'expect' => 'STARTTLS',
                'reply' => "220 go ahead\r\n",
                'then' => 'inject',
                'bytes' => "250-injected\r\n250 AUTH PLAIN\r\n",
            ],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::StartTls));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertNull($failure->reason());
        $this->assertServerClean();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badCertificates(): array
    {
        return [
            'untrusted' => ['untrusted'],
            'wrong name' => ['wrong-name'],
        ];
    }

    #[DataProvider('badCertificates')]
    public function testImplicitTlsWithABadCertificateIsATlsFailure(string $cert): void
    {
        $this->server = FakeSmtpServer::start([
            'mode' => 'ssl',
            'cert' => $cert,
            'steps' => [['reply' => "220 secure\r\n"]],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Implicit));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Connect, $failure->step());
        $this->assertNotEmpty($failure->reason());
        $this->assertSame('SMTP Connect: SMTP_TLS_FAILED', $failure->getMessage());
    }

    #[DataProvider('badCertificates')]
    public function testStartTlsWithABadCertificateIsATlsFailure(string $cert): void
    {
        $this->server = FakeSmtpServer::start([
            'cert' => $cert,
            'steps' => [
                ['reply' => "220 fake ready\r\n"],
                ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 STARTTLS\r\n"],
                ['expect' => 'STARTTLS', 'reply' => "220 go ahead\r\n", 'then' => 'starttls'],
            ],
        ]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::StartTls));

        $this->assertSame(FailureCode::TlsFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::StartTls, $failure->step());
        $this->assertNotEmpty($failure->reason());
    }

    public function testStartTlsSessionOnTheFakeServerDelivers(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 STARTTLS\r\n"],
            ['expect' => 'STARTTLS', 'reply' => "220 go ahead\r\n", 'then' => 'starttls'],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH LOGIN\r\n"],
            ['expect' => 'AUTH LOGIN', 'reply' => "334 VXNlcm5hbWU6\r\n"],
            ['expect' => base64_encode('user'), 'reply' => "334 UGFzc3dvcmQ6\r\n"],
            ['expect' => base64_encode(self::CANARY), 'reply' => "235 ok\r\n"],
            ...$this->envelopeSteps(),
            ['expect' => '.', 'reply' => "250 queued\r\n"],
            ['expect' => 'QUIT', 'reply' => "221 bye\r\n"],
        ]);

        $result = $this->sendToServer(TlsMode::StartTls, $this->canaryCredentials());

        $this->assertSame(250, $result->code());
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

    public function testHangingGreetingIsATimeout(): void
    {
        $this->startServer([['then' => 'hang']]);
        $settings = $this->fakeSettings(connectTimeout: self::SHORT_TIMEOUT);
        $started = microtime(true);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Plain, settings: $settings));

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
        $this->assertLessThan(self::SHORT_TIMEOUT + 1.0, microtime(true) - $started);
    }

    public function testHangingReplyIsBoundByTheCommandTimeout(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'then' => 'hang'],
        ]);
        $settings = $this->fakeSettings(commandTimeout: self::SHORT_TIMEOUT);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Plain, settings: $settings));

        $this->assertSame(FailureCode::Timeout, $failure->failureCode());
        $this->assertSame(SmtpStep::Ehlo, $failure->step());
    }

    public function testCloseBeforeTheGreetingIsAConnectionFailure(): void
    {
        $this->startServer([['then' => 'close']]);

        $failure = $this->failure(fn () => $this->sendToServer(TlsMode::Plain));

        $this->assertSame(FailureCode::ConnectionFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Greeting, $failure->step());
        $this->assertServerClean();
    }

    public function testClosedPortIsAConnectionFailure(): void
    {
        $target = new Target('localhost', '127.0.0.1', $this->closedPort(), TlsMode::Plain);

        $failure = $this->failure(fn () => $this->transport->send(
            $target,
            $this->fakeSettings(),
            null,
            new Envelope(self::FROM, [self::TO]),
            $this->message('closed'),
        ));

        $this->assertSame(FailureCode::ConnectionFailed, $failure->failureCode());
        $this->assertSame(SmtpStep::Connect, $failure->step());
    }

    /**
     * @param list<array<string, mixed>> $steps
     */
    private function startServer(array $steps): void
    {
        $this->server = FakeSmtpServer::start(['steps' => $steps]);
    }

    private function assertServerClean(): void
    {
        $this->assertNotNull($this->server);
        $this->server->assertClean();
    }

    /**
     * The server steps of `MAIL FROM`, `RCPT TO`, `DATA` and the message lines, up to the `.`.
     *
     * @return list<array<string, mixed>>
     */
    private function envelopeSteps(): array
    {
        return [
            ['expect' => 'MAIL FROM:<' . self::FROM . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'RCPT TO:<' . self::TO . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'DATA', 'reply' => "354 go ahead\r\n"],
            ['expect' => 'Subject: fake'],
            ['expect' => ''],
            ['expect' => 'body'],
            ['expect' => '..'],
        ];
    }

    private function sendToServer(
        TlsMode $mode,
        ?Credentials $credentials = null,
        ?SessionSettings $settings = null,
    ): SmtpResult {
        $this->assertNotNull($this->server);

        return $this->transport->send(
            new Target('localhost', $this->server->host(), $this->server->port(), $mode),
            $settings ?? $this->fakeSettings(),
            $credentials,
            new Envelope(self::FROM, [self::TO]),
            "Subject: fake\r\n\r\nbody\r\n.\r\n",
        );
    }

    private function sendTo(
        Mailpit $mailpit,
        TlsMode $mode,
        ?Credentials $credentials,
        ?string $message = null,
        ?SessionSettings $settings = null,
    ): SmtpResult {
        $address = gethostbyname($mailpit->host());
        $this->assertNotFalse(filter_var($address, FILTER_VALIDATE_IP), "cannot resolve {$mailpit->host()}");

        return $this->transport->send(
            new Target($mailpit->host(), $address, $mailpit->smtpPort(), $mode),
            $settings ?? $this->mailpitSettings(),
            $credentials,
            new Envelope(self::FROM, [self::TO]),
            $message ?? $this->message('unused'),
        );
    }

    private function message(string $subject): string
    {
        return "From: <" . self::FROM . ">\r\nTo: <" . self::TO . ">\r\nSubject: {$subject}\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n\r\n"
            . "first line\r\n" . self::DOT_LINE . "\r\n.\r\nlast line\r\n";
    }

    private function fakeSettings(
        float $connectTimeout = self::TIMEOUT,
        float $commandTimeout = self::TIMEOUT,
    ): SessionSettings {
        $caFile = $this->fixtureCa();

        return new SessionSettings($connectTimeout, $commandTimeout, self::TIMEOUT, $caFile, self::EHLO_NAME);
    }

    /**
     * Mailpit settings: the CA of `SMTP_CA_FILE`, and the local address literal in `EHLO`.
     */
    private function mailpitSettings(float $connectTimeout = self::TIMEOUT): SessionSettings
    {
        return new SessionSettings($connectTimeout, self::TIMEOUT, self::TIMEOUT, $this->configuredCaFile(), null);
    }

    private function canaryCredentials(): Credentials
    {
        return new Credentials('user', new SecretString(self::CANARY));
    }

    private function fixtureCa(): string
    {
        return (string) realpath(self::FIXTURE_CA);
    }

    /**
     * The CA bundle of the test environment (`SMTP_CA_FILE`), as the app resolves it.
     */
    private function configuredCaFile(): string
    {
        $caFile = Env::get('SMTP_CA_FILE');
        $this->assertNotNull($caFile, 'SMTP_CA_FILE must be set in the test environment');

        return AppPath::resolve($caFile);
    }

    private function closedPort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($server);
        $name = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
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

    /**
     * Fails when the failure message, or a message of its `previous` chain, carries the canary
     * password or one of its base64 forms (alone, in the `PLAIN` payload or the `LOGIN` line).
     */
    private function assertNoCanary(Throwable $failure, string $username = 'user'): void
    {
        $secrets = [
            self::CANARY,
            base64_encode(self::CANARY),
            base64_encode("\0{$username}\0" . self::CANARY),
        ];

        for ($current = $failure; $current !== null; $current = $current->getPrevious()) {
            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $current->getMessage());
            }
        }
    }
}
