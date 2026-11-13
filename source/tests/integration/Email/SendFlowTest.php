<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Email;

use DateTimeImmutable;
use LogicException;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Crypto\DecryptionFailed;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Email\Message;
use Pidgey\Email\MimeBuilder;
use Pidgey\Email\ReplyText;
use Pidgey\Email\SendActor;
use Pidgey\Email\SendFlow;
use Pidgey\Email\SendOutcome;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;
use Pidgey\Http\SmtpFailureException;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\SecretString;
use Pidgey\Infra\SystemClock;
use Pidgey\Infra\WallClock;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Deadline;
use Pidgey\Smtp\EhloExtensions;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\HostCheck;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\MalformedReply;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\SmtpSessionState;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\SocketStream;
use Pidgey\Smtp\StreamFailure;
use Pidgey\Smtp\SystemHostResolver;
use Pidgey\Smtp\Target;
use Pidgey\Smtp\TlsContext;
use Pidgey\Tests\Support\CapturedLog;
use Pidgey\Tests\Support\FakeSmtpServer;
use Pidgey\Tests\Support\FrozenWallClock;
use Pidgey\Tests\Support\Mailpit;

/**
 * End-to-end tests of the send flow over a real transport: the Mailpit instances for delivery
 * and authentication, `FakeSmtpServer` for one case per transport failure code. Every outcome
 * goes through `SmtpFailureException` and `ErrorFormatter`, and a canary password is checked
 * absent from the outcome, the exception, the JSON body and the log.
 */
#[CoversClass(SendFlow::class)]
#[CoversClass(SmtpFailureException::class)]
#[UsesClass(AppPath::class)]
#[UsesClass(Credentials::class)]
#[UsesClass(Deadline::class)]
#[UsesClass(DecryptionFailed::class)]
#[UsesClass(EhloExtensions::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(Env::class)]
#[UsesClass(Envelope::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(HostCheck::class)]
#[UsesClass(HostPolicy::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(LoggerFactory::class)]
#[UsesClass(MalformedReply::class)]
#[UsesClass(Message::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(PasswordCipher::class)]
#[UsesClass(Reply::class)]
#[UsesClass(ReplyParser::class)]
#[UsesClass(ReplyText::class)]
#[UsesClass(Response::class)]
#[UsesClass(SecretString::class)]
#[UsesClass(SendActor::class)]
#[UsesClass(SendOutcome::class)]
#[UsesClass(SessionSettings::class)]
#[UsesClass(SmtpConnection::class)]
#[UsesClass(SmtpFailure::class)]
#[UsesClass(SmtpResult::class)]
#[UsesClass(SmtpSessionState::class)]
#[UsesClass(SmtpTransport::class)]
#[UsesClass(SocketStream::class)]
#[UsesClass(StreamFailure::class)]
#[UsesClass(SystemHostResolver::class)]
#[UsesClass(Target::class)]
#[UsesClass(TlsContext::class)]
final class SendFlowTest extends TestCase
{
    private const CANARY = 'canary-Pw-165-é';
    private const USERNAME = 'canary-user';
    private const KEY = 'send-flow-integration-smtp-encryption-key-0123456789';
    private const ORGANIZATION = 31;
    private const CONNECTION = 47;
    private const FROM = 'sender@pidgey.test';
    private const TO = 'recipient@pidgey.test';
    private const SUBJECT = 'Résumé of the canary flight';
    private const TEXT = 'Plain body';
    private const HTML = '<p>Rich body</p>';
    private const EHLO_NAME = 'client.example';
    private const FIXTURE_CA = __DIR__ . '/../../fixtures/tls/ca.pem';
    private const TIMEOUT = 5.0;
    private const SHORT_TIMEOUT = 0.5;
    private const TIMESTAMP = '2026-10-25T12:00:00.000Z';

    private CapturedLog $log;

    private ?FakeSmtpServer $server = null;

    protected function setUp(): void
    {
        $this->log = new CapturedLog();

        foreach ([Mailpit::plain(), Mailpit::starttls(), Mailpit::ssl()] as $mailpit) {
            $mailpit->clear();
        }
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    // Mailpit: delivery.

    /**
     * @return array<string, array{string, SmtpEncryption, bool}>
     */
    public static function mailpitDeliveries(): array
    {
        return [
            'plain, no credentials' => ['plain', SmtpEncryption::None, false],
            'STARTTLS with credentials' => ['starttls', SmtpEncryption::Tls, true],
            'implicit TLS with credentials' => ['ssl', SmtpEncryption::Ssl, true],
        ];
    }

    #[DataProvider('mailpitDeliveries')]
    public function testDeliversThroughMailpit(string $instance, SmtpEncryption $encryption, bool $authenticate): void
    {
        $mailpit = Mailpit::$instance();
        $connection = $this->connection(
            $mailpit->host(),
            $mailpit->smtpPort(),
            $encryption,
            $authenticate ? Mailpit::AUTH_USER : null,
            $authenticate ? Mailpit::AUTH_PASSWORD : null,
        );

        $outcome = $this->flow($this->mailpitSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertTrue($outcome->isSent());
        $this->assertSame(250, $outcome->replyCode());
        $this->assertNotSame('', (string) $outcome->replyMessage());

        $messages = $mailpit->messages();
        $this->assertCount(1, $messages);
        $message = $mailpit->message((string) $messages[0]['ID']);
        $this->assertSame(self::SUBJECT, $message['Subject']);
        $this->assertSame(self::FROM, $message['From']['Address']);
        $this->assertSame(self::TO, $message['To'][0]['Address']);
        $this->assertSame(self::TEXT, trim((string) $message['Text']));
        $this->assertSame(self::HTML, trim((string) $message['HTML']));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}@pidgey\.test$/', (string) $message['MessageID']);

        $this->assertLogged('info', [
            'organizationId' => self::ORGANIZATION,
            'connectionId' => self::CONNECTION,
            'userId' => 3,
            'recipient' => self::TO,
            'outcome' => 'sent',
            'replyCode' => 250,
            'reply' => $outcome->replyMessage(),
        ]);
    }

    // Mailpit: authentication.

    public function testWrongCredentialsAreAnAuthFailure(): void
    {
        $mailpit = Mailpit::starttls();
        $connection = $this->connection(
            $mailpit->host(),
            $mailpit->smtpPort(),
            SmtpEncryption::Tls,
            Mailpit::AUTH_USER,
        );

        $outcome = $this->flow($this->mailpitSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_AUTH_FAILED', $outcome->failureCode());
        $this->assertSame('auth', $outcome->step());
        $this->assertSame(535, $outcome->replyCode());
        $reply = $outcome->replyMessage();
        $this->assertNotNull($reply);
        $this->assertSame(
            $this->body(502, 'SMTP_AUTH_FAILED', 'The SMTP server rejected the credentials', 535, $reply),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection, Mailpit::AUTH_USER);
        $this->assertSame([], $mailpit->messages());
    }

    public function testNoCredentialsOnTheAuthInstanceRequireAuthentication(): void
    {
        $mailpit = Mailpit::starttls();
        $connection = $this->connection($mailpit->host(), $mailpit->smtpPort(), SmtpEncryption::Tls, null, null);

        $outcome = $this->flow($this->mailpitSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_AUTH_FAILED', $outcome->failureCode());
        $this->assertSame('mail-from', $outcome->step());
        $this->assertSame(530, $outcome->replyCode());
        $reply = $outcome->replyMessage();
        $this->assertNotNull($reply);
        $this->assertSame(
            $this->body(502, 'SMTP_AUTH_FAILED', 'The SMTP server requires authentication', 530, $reply),
            $this->formatted($outcome, $connection),
        );
    }

    // FakeSmtpServer: success and one case per transport failure code.

    public function testSendsWithTheCanaryAndRedactsItsEcho(): void
    {
        $this->startServer([
            ...$this->authenticatedSteps(),
            ['expect' => 'MAIL FROM:<' . self::FROM . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'RCPT TO:<' . self::TO . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'DATA', 'reply' => "354 go\r\n"],
            ['expectPrefix' => 'Date: '],
            ...$this->skipUntilDot(),
            ['expect' => '.', 'reply' => '250 2.0.0 queued for ' . self::CANARY . "\r\n"],
            ['expect' => 'QUIT', 'reply' => "221 bye\r\n"],
        ]);

        $connection = $this->fakeConnection(SmtpEncryption::Ssl);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::token(8));

        $this->assertTrue($outcome->isSent());
        $this->assertSame('2.0.0 queued for [redacted]', $outcome->replyMessage());
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }

    public function testClosedPortIsAConnectionFailure(): void
    {
        $connection = $this->connection('localhost', $this->closedPort(), SmtpEncryption::Ssl, self::USERNAME);

        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_CONNECTION_FAILED', $outcome->failureCode());
        $this->assertSame(
            $this->body(502, 'SMTP_CONNECTION_FAILED', 'Could not connect to the SMTP server'),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
    }

    public function testStartTlsNotAdvertisedIsATlsFailure(): void
    {
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH PLAIN\r\n"],
            ['expect' => 'QUIT', 'reply' => "221 bye\r\n"],
        ], 'plain');

        $connection = $this->fakeConnection(SmtpEncryption::Tls);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_TLS_FAILED', $outcome->failureCode());
        $this->assertSame('starttls', $outcome->step());
        $this->assertSame(
            $this->body(502, 'SMTP_TLS_FAILED', 'TLS negotiation with the SMTP server failed'),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }

    public function testEchoedAuthPayloadIsRedacted(): void
    {
        $payload = self::authPayload();
        $this->startServer([
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH PLAIN LOGIN\r\n"],
            [
                'expect' => 'AUTH PLAIN ' . $payload,
                'reply' => "535-5.7.8 AUTH PLAIN {$payload}\r\n535 5.7.8 bad " . self::CANARY . "\r\n",
            ],
        ]);

        $connection = $this->fakeConnection(SmtpEncryption::Ssl);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_AUTH_FAILED', $outcome->failureCode());
        $this->assertSame('5.7.8 AUTH PLAIN [redacted] bad [redacted]', $outcome->replyMessage());
        $this->assertSame(
            $this->body(
                502,
                'SMTP_AUTH_FAILED',
                'The SMTP server rejected the credentials',
                535,
                '5.7.8 AUTH PLAIN [redacted] bad [redacted]',
            ),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }

    public function testRejectedRecipient(): void
    {
        $this->startServer([
            ...$this->authenticatedSteps(),
            ['expect' => 'MAIL FROM:<' . self::FROM . '>', 'reply' => "250 ok\r\n"],
            ['expect' => 'RCPT TO:<' . self::TO . '>', 'reply' => "550 5.1.1 User\tunknown\r\n"],
        ]);

        $connection = $this->fakeConnection(SmtpEncryption::Ssl);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame(
            $this->body(502, 'SMTP_REJECTED', 'The SMTP server rejected the recipient', 550, '5.1.1 User unknown'),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }

    public function testTemporaryFailureAtTheGreeting(): void
    {
        $this->startServer([['reply' => "421 4.3.2 busy\r\n"]]);

        $connection = $this->fakeConnection(SmtpEncryption::Ssl);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('greeting', $outcome->step());
        $this->assertSame(
            $this->body(
                503,
                'SMTP_TEMPORARY_FAILURE',
                'The SMTP server is temporarily unavailable; try again later',
                421,
                '4.3.2 busy',
            ),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }

    public function testMalformedReplyIsAProtocolError(): void
    {
        $this->startServer([
            ...$this->authenticatedSteps(),
            ['expect' => 'MAIL FROM:<' . self::FROM . '>', 'reply' => "250-ok\r\n251 mixed\r\n"],
        ]);

        $connection = $this->fakeConnection(SmtpEncryption::Ssl);
        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_PROTOCOL_ERROR', $outcome->failureCode());
        $this->assertSame('mail-from', $outcome->step());
        $this->assertSame(
            $this->body(502, 'SMTP_PROTOCOL_ERROR', 'The SMTP server sent an invalid reply'),
            $this->formatted($outcome, $connection),
        );
        $this->assertNoCanary($outcome, $connection);
        $this->server?->assertClean();
    }

    public function testSilentServerIsATimeoutWithThePortHint(): void
    {
        $this->startServer([['then' => 'hang']], 'plain');

        $connection = $this->connection('localhost', (int) $this->server?->port(), SmtpEncryption::Tls, self::USERNAME);
        $short = self::SHORT_TIMEOUT;
        $settings = new SessionSettings($short, $short, self::TIMEOUT, self::FIXTURE_CA, self::EHLO_NAME);
        $outcome = $this->flow($settings)->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_TIMEOUT', $outcome->failureCode());
        $this->assertSame('greeting', $outcome->step());
        $this->assertSame(
            $this->body(504, 'SMTP_TIMEOUT', 'The SMTP server timed out'),
            $this->formatted($outcome, $connection),
        );
        $this->assertSame(
            $this->body(504, 'SMTP_TIMEOUT', 'The SMTP server timed out; port 465 usually requires encryption ssl'),
            $this->json(SmtpFailureException::fromOutcome($outcome, 465, SmtpEncryption::Tls)),
        );
        $this->assertNoCanary($outcome, $connection);
    }

    // Send-time checks: the server is never contacted.

    public function testAPrivateHostIsNotAllowed(): void
    {
        [$listener, $port] = $this->listener();
        $connection = $this->connection('127.0.0.1', $port, SmtpEncryption::Ssl, self::USERNAME);

        $outcome = $this->flow($this->fakeSettings(), false)->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame(
            $this->body(409, 'SMTP_HOST_NOT_ALLOWED', 'The SMTP host resolves to an address that is not allowed'),
            $this->formatted($outcome, $connection),
        );
        $this->assertNotContacted($listener);
        $this->assertNoCanary($outcome, $connection);
    }

    public function testAPrivateHostNameIsNotAllowed(): void
    {
        [$listener, $port] = $this->listener();
        $connection = $this->connection('localhost', $port, SmtpEncryption::Ssl, self::USERNAME);

        $outcome = $this->flow($this->fakeSettings(), false)->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame('SMTP_HOST_NOT_ALLOWED', $outcome->failureCode());
        $this->assertNotContacted($listener);
    }

    public function testInsecureCredentialsAreRefused(): void
    {
        [$listener, $port] = $this->listener();
        $connection = $this->connection('localhost', $port, SmtpEncryption::None, self::USERNAME);

        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame(
            $this->body(
                409,
                'SMTP_INSECURE_CREDENTIALS',
                'The SMTP connection has credentials but no encryption; set encryption to tls or ssl',
            ),
            $this->formatted($outcome, $connection),
        );
        $this->assertNotContacted($listener);
        $this->assertNoCanary($outcome, $connection);
    }

    public function testAnUnreadablePasswordIsRefused(): void
    {
        [$listener, $port] = $this->listener();
        $stored = (new PasswordCipher(new EncryptionKeys(self::KEY . '-other')))
            ->encrypt(self::CANARY, self::ORGANIZATION, self::CONNECTION);
        $connection = $this->connection('localhost', $port, SmtpEncryption::Ssl, self::USERNAME, stored: $stored);

        $outcome = $this->flow($this->fakeSettings())->send($connection, $this->message(), SendActor::user(3));

        $this->assertSame(
            $this->body(
                409,
                'SMTP_PASSWORD_UNREADABLE',
                'The stored SMTP password could not be decrypted; set it again',
            ),
            $this->formatted($outcome, $connection),
        );
        $this->assertNotContacted($listener);
        $this->assertNoCanary($outcome, $connection);
    }

    public function testASentOutcomeIsNotAnError(): void
    {
        $this->expectException(LogicException::class);

        SmtpFailureException::fromOutcome(SendOutcome::sent(250, 'ok'), 25, SmtpEncryption::None);
    }

    /**
     * The greeting, `EHLO` and a successful `AUTH PLAIN` with the canary.
     *
     * @return list<array<string, string>>
     */
    private function authenticatedSteps(): array
    {
        return [
            ['reply' => "220 fake ready\r\n"],
            ['expect' => 'EHLO ' . self::EHLO_NAME, 'reply' => "250-fake\r\n250 AUTH PLAIN LOGIN\r\n"],
            ['expect' => 'AUTH PLAIN ' . self::authPayload(), 'reply' => "235 2.7.0 ok\r\n"],
        ];
    }

    /**
     * Reads the MIME lines after `Date:` up to (not including) the final `.`: the headers are
     * fixed in number, then the multipart body.
     *
     * @return list<array<string, string>>
     */
    private function skipUntilDot(): array
    {
        $lines = explode("\r\n", rtrim($this->mimeTemplate(), "\r\n"));

        return array_map(
            static fn (string $line): array => ['expectPrefix' => self::stablePrefix($line)],
            array_slice($lines, 1),
        );
    }

    /**
     * The message the flow builds, minus its random parts (each line's stable prefix is used).
     */
    private function mimeTemplate(): string
    {
        $mime = new MimeBuilder(new FrozenWallClock(1_800_000_000), static fn (): string => str_repeat("\0", 16));

        return $mime->build(self::FROM, $this->message());
    }

    private static function stablePrefix(string $line): string
    {
        foreach (['Message-ID: <', 'Content-Type: multipart/alternative', '--=_pidgey_'] as $prefix) {
            if (str_starts_with($line, $prefix)) {
                return $prefix;
            }
        }

        return $line;
    }

    private static function authPayload(): string
    {
        return base64_encode("\0" . self::USERNAME . "\0" . self::CANARY);
    }

    /**
     * @param list<array<string, mixed>> $steps
     */
    private function startServer(array $steps, string $mode = 'ssl'): void
    {
        $this->server = FakeSmtpServer::start(['mode' => $mode, 'steps' => $steps]);
    }

    private function fakeConnection(SmtpEncryption $encryption): SmtpConnection
    {
        $this->assertNotNull($this->server);

        return $this->connection('localhost', $this->server->port(), $encryption, self::USERNAME);
    }

    private function flow(SessionSettings $settings, bool $allowPrivate = true): SendFlow
    {
        return new SendFlow(
            new HostPolicy(new SystemHostResolver(), $allowPrivate),
            $this->cipher(),
            new MimeBuilder(new FrozenWallClock(1_800_000_000)),
            new SmtpTransport(new SystemClock()),
            $settings,
            $this->log->logger(),
        );
    }

    private function cipher(): PasswordCipher
    {
        return new PasswordCipher(new EncryptionKeys(self::KEY));
    }

    private function message(): Message
    {
        return new Message(self::TO, self::SUBJECT, self::TEXT, self::HTML);
    }

    private function connection(
        string $host,
        int $port,
        SmtpEncryption $encryption,
        ?string $username,
        ?string $password = self::CANARY,
        ?string $stored = null,
    ): SmtpConnection {
        if ($stored === null && $password !== null) {
            $stored = $this->cipher()->encrypt($password, self::ORGANIZATION, self::CONNECTION);
        }

        return new SmtpConnection(
            self::CONNECTION,
            self::ORGANIZATION,
            'Relay',
            $host,
            $port,
            $encryption,
            $username,
            $stored,
            self::FROM,
            true,
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-01-01'),
        );
    }

    private function fakeSettings(): SessionSettings
    {
        return new SessionSettings(self::TIMEOUT, self::TIMEOUT, self::TIMEOUT, self::FIXTURE_CA, self::EHLO_NAME);
    }

    private function mailpitSettings(): SessionSettings
    {
        $caFile = Env::get('SMTP_CA_FILE');
        $this->assertNotNull($caFile, 'SMTP_CA_FILE must be set in the test environment');

        return new SessionSettings(self::TIMEOUT, self::TIMEOUT, self::TIMEOUT, AppPath::resolve($caFile), null);
    }

    private function formatted(SendOutcome $outcome, SmtpConnection $connection): string
    {
        $exception = SmtpFailureException::fromOutcome($outcome, $connection->port, $connection->encryption);

        $this->assertNoSecretIn($exception->getMessage(), $connection->username ?? self::USERNAME);

        return $this->json($exception);
    }

    private function json(SmtpFailureException $exception): string
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-25T12:00:00.000Z');
            }
        };

        return (new ErrorFormatter($clock, $this->log->logger()))->format($exception)->body();
    }

    private function body(
        int $status,
        string $code,
        string $message,
        ?int $replyCode = null,
        ?string $reply = null,
    ): string {
        $error = ['code' => $code, 'message' => $message];

        if ($replyCode !== null) {
            $error['smtp'] = ['code' => $replyCode, 'message' => $reply];
        }

        return (string) json_encode(
            ['error' => $error, 'statusCode' => $status, 'timestamp' => self::TIMESTAMP],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Checks the canary (plain, base64, and inside the `AUTH PLAIN` payload) is absent from the
     * outcome, the exception, the formatted body and every log record.
     */
    private function assertNoCanary(
        SendOutcome $outcome,
        SmtpConnection $connection,
        string $username = self::USERNAME,
    ): void {
        $this->assertNoSecretIn(
            (string) json_encode([
                $outcome->failureCode(),
                $outcome->step(),
                $outcome->replyCode(),
                $outcome->replyMessage(),
                $outcome->tlsReason(),
            ], JSON_UNESCAPED_UNICODE),
            $username,
        );

        if (!$outcome->isSent()) {
            $exception = SmtpFailureException::fromOutcome($outcome, $connection->port, $connection->encryption);
            $this->assertNoSecretIn($exception->getMessage(), $username);
            $this->assertNoSecretIn($this->json($exception), $username);
        }

        $records = $this->log->records();
        $this->assertNotSame([], $records);

        foreach ($records as $record) {
            $this->assertNoSecretIn(
                (string) json_encode(
                    ['message' => $record->message, 'context' => $record->context],
                    JSON_UNESCAPED_UNICODE,
                ),
                $username,
            );
            $this->assertNoSecretIn((string) $record->formatted, $username);
        }
    }

    private function assertNoSecretIn(string $haystack, string $username): void
    {
        $forms = [
            'plain' => self::CANARY,
            'json-escaped' => trim((string) json_encode(self::CANARY), '"'),
            'base64' => base64_encode(self::CANARY),
            'auth payload' => base64_encode("\0{$username}\0" . self::CANARY),
        ];

        foreach ($forms as $form => $secret) {
            $this->assertFalse(str_contains($haystack, $secret), "canary found ({$form})");
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function assertLogged(string $level, array $context): void
    {
        $records = array_values(array_filter(
            $this->log->records(),
            static fn (LogRecord $record): bool => $record->message === 'smtp-send-attempt',
        ));

        $this->assertCount(1, $records);
        $this->assertSame($level, strtolower($records[0]->level->getName()));
        $this->assertSame($context, $records[0]->context);
    }

    /**
     * A listening socket nobody should connect to, and its port.
     *
     * @return array{0: resource, 1: int}
     */
    private function listener(): array
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($listener);
        $name = (string) stream_socket_get_name($listener, false);

        return [$listener, (int) substr($name, (int) strrpos($name, ':') + 1)];
    }

    /**
     * @param resource $listener
     */
    private function assertNotContacted($listener): void
    {
        $this->assertFalse(@stream_socket_accept($listener, 0), 'the server was contacted');
        fclose($listener);
    }

    private function closedPort(): int
    {
        [$listener, $port] = $this->listener();
        fclose($listener);

        return $port;
    }
}
