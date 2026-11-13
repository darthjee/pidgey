<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Email;

use Closure;
use DateTimeImmutable;
use LogicException;
use Monolog\Level;
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
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\SecretString;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\FailureCode;
use Pidgey\Smtp\HostCheck;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\HostResolver;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\SmtpStep;
use Pidgey\Smtp\Target;
use Pidgey\Smtp\TlsMode;
use Pidgey\Smtp\Transport;
use Pidgey\Tests\Support\CapturedLog;
use Pidgey\Tests\Support\FrozenWallClock;

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
final class SendFlowTest extends TestCase
{
    private const KEY = 'send-flow-test-smtp-encryption-key-0123456789abcdef';
    private const OTHER_KEY = 'send-flow-test-other-smtp-encryption-key-0123456789';
    private const PASSWORD = 'canary-7Hq2-pass';
    private const USERNAME = 'relay-user';
    private const ORGANIZATION = 7;
    private const CONNECTION = 11;
    private const HOST = 'smtp.example.com';
    private const PUBLIC_IP = '93.184.216.34';
    private const SUBJECT = 'Secret subject line';
    private const BODY = 'Secret body text';
    private const RECIPIENT = 'to@example.org';

    private CapturedLog $log;

    /** @var list<array{target: Target, credentials: ?Credentials, envelope: Envelope, message: string}> */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->log = new CapturedLog();
        $this->calls = [];
    }

    public function testSendsAndReturnsTheCleanedReply(): void
    {
        $flow = $this->flow(static fn (): SmtpResult => new SmtpResult(new Reply(250, ['2.0.0 Ok:  queued'])));

        $outcome = $flow->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertTrue($outcome->isSent());
        $this->assertNull($outcome->failureCode());
        $this->assertNull($outcome->step());
        $this->assertSame(250, $outcome->replyCode());
        $this->assertSame('2.0.0 Ok: queued', $outcome->replyMessage());
        $this->assertNull($outcome->tlsReason());
    }

    public function testPassesTheCheckedIpHostPortModeEnvelopeAndMime(): void
    {
        $flow = $this->flow($this->ok(), ['2001:db8::1', self::PUBLIC_IP]);

        $connection = $this->connection(port: 2525, encryption: SmtpEncryption::Ssl);

        $flow->send($connection, $this->message(), SendActor::user(5));

        $this->assertCount(1, $this->calls);
        $call = $this->calls[0];
        $this->assertSame(self::HOST, $call['target']->host);
        $this->assertSame(self::PUBLIC_IP, $call['target']->address);
        $this->assertSame(2525, $call['target']->port);
        $this->assertSame(TlsMode::Implicit, $call['target']->tlsMode);
        $this->assertSame('sender@example.com', $call['envelope']->from);
        $this->assertSame([self::RECIPIENT], $call['envelope']->recipients);
        $this->assertStringContainsString("From: sender@example.com\r\nTo: " . self::RECIPIENT, $call['message']);
        $this->assertStringContainsString('Subject: ' . self::SUBJECT, $call['message']);
        $this->assertSame(self::USERNAME, $call['credentials']?->username());
    }

    /**
     * @return iterable<string, array{0: SmtpEncryption, 1: TlsMode}>
     */
    public static function encryptions(): iterable
    {
        yield 'none' => [SmtpEncryption::None, TlsMode::Plain];
        yield 'tls' => [SmtpEncryption::Tls, TlsMode::StartTls];
        yield 'ssl' => [SmtpEncryption::Ssl, TlsMode::Implicit];
    }

    #[DataProvider('encryptions')]
    public function testMapsTheEncryptionToTheTlsMode(SmtpEncryption $encryption, TlsMode $mode): void
    {
        $connection = $this->connection(encryption: $encryption, username: null, password: null);

        $this->flow($this->ok())->send($connection, $this->message(), SendActor::user(5));

        $this->assertSame($mode, $this->calls[0]['target']->tlsMode);
    }

    public function testSendsWithoutCredentialsWhenNoneAreStored(): void
    {
        $connection = $this->connection(encryption: SmtpEncryption::None, username: null, password: null);

        $outcome = $this->flow($this->ok())->send($connection, $this->message(), SendActor::user(5));

        $this->assertTrue($outcome->isSent());
        $this->assertNull($this->calls[0]['credentials']);
    }

    public function testSendsWithoutCredentialsWhenOnlyAUsernameIsStored(): void
    {
        $connection = $this->connection(encryption: SmtpEncryption::None, password: null);

        $this->flow($this->ok())->send($connection, $this->message(), SendActor::user(5));

        $this->assertNull($this->calls[0]['credentials']);
    }

    public function testDecryptsThePasswordForTheTransport(): void
    {
        $seen = null;
        $flow = $this->flow(static function (?Credentials $credentials) use (&$seen): SmtpResult {
            $seen = $credentials?->password()->reveal();

            return new SmtpResult(new Reply(250, ['Ok']));
        });

        $flow->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertSame(self::PASSWORD, $seen);
    }

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

    public function testACorruptPasswordIsUnreadable(): void
    {
        $outcome = $this->flow($this->ok())
            ->send($this->connection(password: 'garbage'), $this->message(), SendActor::user(5));

        $this->assertSendTimeFailure('SMTP_PASSWORD_UNREADABLE', $outcome);
    }

    public function testAPasswordUnderAnUnknownKeyIsUnreadable(): void
    {
        $stored = (new PasswordCipher(new EncryptionKeys(self::OTHER_KEY)))
            ->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);

        $outcome = $this->flow($this->ok())
            ->send($this->connection(password: $stored), $this->message(), SendActor::user(5));

        $this->assertSendTimeFailure('SMTP_PASSWORD_UNREADABLE', $outcome);
    }

    public function testAPasswordBoundToAnotherRowIsUnreadable(): void
    {
        $stored = $this->cipher()->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION + 1);

        $outcome = $this->flow($this->ok())
            ->send($this->connection(password: $stored), $this->message(), SendActor::user(5));

        $this->assertSendTimeFailure('SMTP_PASSWORD_UNREADABLE', $outcome);
    }

    /**
     * @return iterable<string, array{0: SmtpFailure, 1: string, 2: string, 3: ?int, 4: ?string, 5: ?string}>
     */
    public static function failures(): iterable
    {
        yield 'connection failed' => [
            new SmtpFailure(FailureCode::ConnectionFailed, SmtpStep::Connect),
            'SMTP_CONNECTION_FAILED', 'connect', null, null, null,
        ];
        yield 'tls failed with reason' => [
            new SmtpFailure(FailureCode::TlsFailed, SmtpStep::StartTls, null, 'certificate verify failed'),
            'SMTP_TLS_FAILED', 'starttls', null, null, 'certificate verify failed',
        ];
        yield 'auth failed' => [
            new SmtpFailure(FailureCode::AuthFailed, SmtpStep::Auth, new Reply(535, ['5.7.8 Bad credentials'])),
            'SMTP_AUTH_FAILED', 'auth', 535, '5.7.8 Bad credentials', null,
        ];
        yield 'greeting rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::Greeting, new Reply(554, ['No'])),
            'SMTP_REJECTED', 'greeting', 554, 'No', null,
        ];
        yield 'ehlo rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::Ehlo, new Reply(550, ['No'])),
            'SMTP_REJECTED', 'ehlo', 550, 'No', null,
        ];
        yield 'sender rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::MailFrom, new Reply(553, ['Bad sender'])),
            'SMTP_REJECTED', 'mail-from', 553, 'Bad sender', null,
        ];
        yield 'recipient rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::RcptTo, new Reply(550, ['5.1.1 User unknown'])),
            'SMTP_REJECTED', 'rcpt-to', 550, '5.1.1 User unknown', null,
        ];
        yield 'data rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::Data, new Reply(554, ['No data'])),
            'SMTP_REJECTED', 'data', 554, 'No data', null,
        ];
        yield 'message rejected' => [
            new SmtpFailure(FailureCode::Rejected, SmtpStep::EndOfData, new Reply(552, ['Too big'])),
            'SMTP_REJECTED', 'end-of-data', 552, 'Too big', null,
        ];
        yield 'temporary failure' => [
            new SmtpFailure(FailureCode::TemporaryFailure, SmtpStep::RcptTo, new Reply(451, ['Try later'])),
            'SMTP_TEMPORARY_FAILURE', 'rcpt-to', 451, 'Try later', null,
        ];
        yield 'protocol error' => [
            new SmtpFailure(FailureCode::ProtocolError, SmtpStep::Ehlo),
            'SMTP_PROTOCOL_ERROR', 'ehlo', null, null, null,
        ];
        yield 'timeout' => [
            new SmtpFailure(FailureCode::Timeout, SmtpStep::Greeting),
            'SMTP_TIMEOUT', 'greeting', null, null, null,
        ];
    }

    #[DataProvider('failures')]
    public function testMapsEachTransportFailure(
        SmtpFailure $failure,
        string $code,
        string $step,
        ?int $replyCode,
        ?string $reply,
        ?string $tlsReason,
    ): void {
        $flow = $this->flow(static fn (): never => throw $failure);

        $outcome = $flow->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertFalse($outcome->isSent());
        $this->assertSame($code, $outcome->failureCode());
        $this->assertSame($step, $outcome->step());
        $this->assertSame($replyCode, $outcome->replyCode());
        $this->assertSame($reply, $outcome->replyMessage());
        $this->assertSame($tlsReason, $outcome->tlsReason());
    }

    public function testRedactsTheEchoedPasswordInAFailureReply(): void
    {
        $payload = base64_encode("\0" . self::USERNAME . "\0" . self::PASSWORD);
        $failure = new SmtpFailure(
            FailureCode::AuthFailed,
            SmtpStep::Auth,
            new Reply(535, ["5.7.8 {$payload} / " . self::PASSWORD . ' / ' . base64_encode(self::PASSWORD)]),
        );

        $outcome = $this->flow(static fn (): never => throw $failure)
            ->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertSame('5.7.8 [redacted] / [redacted] / [redacted]', $outcome->replyMessage());
        $this->log->assertNotLogged(self::PASSWORD);
    }

    public function testRedactsTheEchoedPasswordInASuccessReply(): void
    {
        $flow = $this->flow(static fn (): SmtpResult => new SmtpResult(new Reply(250, ['Ok ' . self::PASSWORD])));

        $outcome = $flow->send($this->connection(), $this->message(), SendActor::user(5));

        $this->assertSame('Ok [redacted]', $outcome->replyMessage());
    }

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

    private function assertSendTimeFailure(string $code, SendOutcome $outcome): void
    {
        $this->assertFalse($outcome->isSent());
        $this->assertSame($code, $outcome->failureCode());
        $this->assertSame('send-time-check', $outcome->step());
        $this->assertNull($outcome->replyCode());
        $this->assertNull($outcome->replyMessage());
        $this->assertNull($outcome->tlsReason());
        $this->assertSame([], $this->calls, 'the transport is never called');
    }

    private function assertWiped(): void
    {
        $credentials = $this->calls[0]['credentials'];
        $this->assertNotNull($credentials);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SecretString: wiped');

        $credentials->password()->reveal();
    }

    /**
     * @param Closure      $behavior  Runs as the transport's send (`Closure(?Credentials): SmtpResult`).
     * @param list<string> $addresses What the stub resolver returns.
     */
    private function flow(Closure $behavior, array $addresses = [self::PUBLIC_IP]): SendFlow
    {
        $resolver = $this->createStub(HostResolver::class);
        $resolver->method('resolve')->willReturn($addresses);

        $calls = &$this->calls;
        $transport = new class ($behavior, $calls) implements Transport {
            /** @param list<array<string, mixed>> $calls */
            public function __construct(private readonly Closure $behavior, private array &$calls)
            {
            }

            public function send(
                Target $target,
                SessionSettings $settings,
                ?Credentials $credentials,
                Envelope $envelope,
                string $message,
            ): SmtpResult {
                $this->calls[] = [
                    'target' => $target,
                    'credentials' => $credentials,
                    'envelope' => $envelope,
                    'message' => $message,
                ];

                return ($this->behavior)($credentials);
            }
        };

        return new SendFlow(
            new HostPolicy($resolver, false),
            $this->cipher(),
            new MimeBuilder(new FrozenWallClock(1_800_000_000)),
            $transport,
            new SessionSettings(5.0, 5.0, 30.0, null, 'pidgey.test'),
            $this->log->logger(),
        );
    }

    private function ok(): Closure
    {
        return static fn (): SmtpResult => new SmtpResult(new Reply(250, ['Ok']));
    }

    private function cipher(): PasswordCipher
    {
        return new PasswordCipher(new EncryptionKeys(self::KEY));
    }

    private function message(): Message
    {
        return new Message(self::RECIPIENT, self::SUBJECT, self::BODY, null);
    }

    private function connection(
        string $host = self::HOST,
        int $port = 587,
        SmtpEncryption $encryption = SmtpEncryption::Tls,
        ?string $username = self::USERNAME,
        ?string $password = '',
    ): SmtpConnection {
        if ($password === '') {
            $password = $this->cipher()->encrypt(self::PASSWORD, self::ORGANIZATION, self::CONNECTION);
        }

        return new SmtpConnection(
            self::CONNECTION,
            self::ORGANIZATION,
            'Relay',
            $host,
            $port,
            $encryption,
            $username,
            $password,
            'sender@example.com',
            true,
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-01-01'),
        );
    }
}
