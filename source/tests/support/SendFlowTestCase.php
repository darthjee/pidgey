<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Email\Message;
use Pidgey\Email\MimeBuilder;
use Pidgey\Email\SendFlow;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\Envelope;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\HostResolver;
use Pidgey\Smtp\Reply;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpResult;
use Pidgey\Smtp\Target;
use Pidgey\Smtp\Transport;

abstract class SendFlowTestCase extends TestCase
{
    use SendFlowAssertions;

    protected const KEY = 'send-flow-test-smtp-encryption-key-0123456789abcdef';
    protected const OTHER_KEY = 'send-flow-test-other-smtp-encryption-key-0123456789';
    protected const PASSWORD = 'canary-7Hq2-pass';
    protected const USERNAME = 'relay-user';
    protected const ORGANIZATION = 7;
    protected const CONNECTION = 11;
    protected const HOST = 'smtp.example.com';
    protected const PUBLIC_IP = '93.184.216.34';
    protected const SUBJECT = 'Secret subject line';
    protected const BODY = 'Secret body text';
    protected const RECIPIENT = 'to@example.org';

    protected CapturedLog $log;

    /**
     * @var list<array{
     *     target: Target, settings: SessionSettings, credentials: ?Credentials, envelope: Envelope, message: string
     * }>
     */
    protected array $calls = [];

    protected function setUp(): void
    {
        $this->log = new CapturedLog();
        $this->calls = [];
    }

    /**
     * @param Closure      $behavior  Runs as the transport's send (`Closure(?Credentials): SmtpResult`).
     * @param list<string> $addresses What the stub resolver returns.
     */
    protected function flow(Closure $behavior, array $addresses = [self::PUBLIC_IP]): SendFlow
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
                    'settings' => $settings,
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

    protected function ok(): Closure
    {
        return static fn (): SmtpResult => new SmtpResult(new Reply(250, ['Ok']));
    }

    protected function cipher(): PasswordCipher
    {
        return new PasswordCipher(new EncryptionKeys(self::KEY));
    }

    protected function message(): Message
    {
        return new Message(self::RECIPIENT, self::SUBJECT, self::BODY, null);
    }

    protected function connection(
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
