<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Closure;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\SecretString;
use Pidgey\Infra\SystemClock;
use Pidgey\Smtp\Credentials;
use Pidgey\Smtp\SmtpFailure;
use Pidgey\Smtp\SmtpTransport;
use Throwable;

/**
 * Shared setup and helpers of the SMTP transport integration tests: the Mailpit instances for
 * delivery and authentication, `FakeSmtpServer` for what Mailpit cannot reproduce.
 *
 * TLS verification is never disabled: the test CA comes from `SMTP_CA_FILE` (Mailpit) or the
 * fixture bundle (`FakeSmtpServer`).
 */
abstract class SmtpTransportIntegrationTestCase extends TestCase
{
    use SmtpTransportMailpitHelpers;
    use SmtpTransportServerHelpers;

    protected const CANARY = 'canary-integration-marker-9e4a';
    protected const FIXTURE_CA = __DIR__ . '/../fixtures/tls/ca.pem';
    protected const EHLO_NAME = 'client.example';
    protected const TIMEOUT = 5.0;
    protected const SHORT_TIMEOUT = 0.5;
    protected const FROM = 'sender@pidgey.test';
    protected const TO = 'recipient@pidgey.test';
    protected const DOT_LINE = '.leading dot';

    protected SmtpTransport $transport;

    protected ?FakeSmtpServer $server = null;

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

    protected function message(string $subject): string
    {
        return "From: <" . self::FROM . ">\r\nTo: <" . self::TO . ">\r\nSubject: {$subject}\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n\r\n"
            . "first line\r\n" . self::DOT_LINE . "\r\n.\r\nlast line\r\n";
    }


    protected function canaryCredentials(): Credentials
    {
        return new Credentials('user', new SecretString(self::CANARY));
    }


    protected function failure(Closure $call): SmtpFailure
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
    protected function assertNoCanary(Throwable $failure, string $username = 'user'): void
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
