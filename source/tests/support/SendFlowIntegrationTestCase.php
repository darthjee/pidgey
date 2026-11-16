<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Email\Message;
use Pidgey\Email\MimeBuilder;
use Pidgey\Email\SendFlow;
use Pidgey\Infra\AppPath;
use Pidgey\Infra\Env;
use Pidgey\Infra\SystemClock;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\SessionSettings;
use Pidgey\Smtp\SmtpTransport;
use Pidgey\Smtp\SystemHostResolver;

abstract class SendFlowIntegrationTestCase extends TestCase
{
    use SendFlowIntegrationAssertions;
    use SendFlowServerSteps;

    protected const CANARY = 'canary-Pw-165-é';
    protected const USERNAME = 'canary-user';
    protected const KEY = 'send-flow-integration-smtp-encryption-key-0123456789';
    protected const ORGANIZATION = 31;
    protected const CONNECTION = 47;
    protected const FROM = 'sender@pidgey.test';
    protected const TO = 'recipient@pidgey.test';
    protected const SUBJECT = 'Résumé of the canary flight';
    protected const TEXT = 'Plain body';
    protected const HTML = '<p>Rich body</p>';
    protected const EHLO_NAME = 'client.example';
    protected const FIXTURE_CA = __DIR__ . '/../fixtures/tls/ca.pem';
    protected const TIMEOUT = 5.0;
    protected const SHORT_TIMEOUT = 0.5;
    protected const TIMESTAMP = '2026-10-25T12:00:00.000Z';

    protected CapturedLog $log;

    protected ?FakeSmtpServer $server = null;

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

    /**
     * @param list<array<string, mixed>> $steps
     */
    protected function startServer(array $steps, string $mode = 'ssl'): void
    {
        $this->server = FakeSmtpServer::start(['mode' => $mode, 'steps' => $steps]);
    }

    protected function fakeConnection(SmtpEncryption $encryption): SmtpConnection
    {
        $this->assertNotNull($this->server);

        return $this->connection('localhost', $this->server->port(), $encryption, self::USERNAME);
    }

    protected function flow(SessionSettings $settings, bool $allowPrivate = true): SendFlow
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

    protected function cipher(): PasswordCipher
    {
        return new PasswordCipher(new EncryptionKeys(self::KEY));
    }

    protected function message(): Message
    {
        return new Message(self::TO, self::SUBJECT, self::TEXT, self::HTML);
    }

    protected function connection(
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

    protected function fakeSettings(): SessionSettings
    {
        return new SessionSettings(self::TIMEOUT, self::TIMEOUT, self::TIMEOUT, self::FIXTURE_CA, self::EHLO_NAME);
    }

    protected function mailpitSettings(): SessionSettings
    {
        $caFile = Env::get('SMTP_CA_FILE');
        $this->assertNotNull($caFile, 'SMTP_CA_FILE must be set in the test environment');

        return new SessionSettings(self::TIMEOUT, self::TIMEOUT, self::TIMEOUT, AppPath::resolve($caFile), null);
    }
}
