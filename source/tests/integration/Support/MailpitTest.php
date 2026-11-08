<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Support;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\Env;
use Pidgey\Tests\Support\Mailpit;

/**
 * Smoke test: each test Mailpit instance is reachable, with verified TLS where it applies.
 */
#[CoversNothing]
final class MailpitTest extends TestCase
{
    private const TIMEOUT_SECONDS = 5;
    private const SENDER = 'sender@pidgey.test';
    private const RECIPIENT = 'recipient@pidgey.test';

    /** @var resource|null */
    private $stream = null;

    protected function tearDown(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
    }

    public function testThePlainInstanceReceivesAMessage(): void
    {
        $mailpit = Mailpit::plain();
        $mailpit->clear();
        $subject = 'Pidgey smoke ' . bin2hex(random_bytes(6));

        $this->connect('tcp', $mailpit);
        $this->command('EHLO pidgey.test', 250);
        $this->command('MAIL FROM:<' . self::SENDER . '>', 250);
        $this->command('RCPT TO:<' . self::RECIPIENT . '>', 250);
        $this->command('DATA', 354);
        $this->command(
            implode("\r\n", [
                'From: <' . self::SENDER . '>',
                'To: <' . self::RECIPIENT . '>',
                "Subject: {$subject}",
                '',
                'Smoke test body.',
                '.',
            ]),
            250,
        );
        $this->command('QUIT', 221);

        $messages = $mailpit->messages();
        $this->assertCount(1, $messages);

        $message = $mailpit->message((string) $messages[0]['ID']);
        $this->assertSame($subject, $message['Subject']);
        $this->assertSame(self::RECIPIENT, $message['To'][0]['Address']);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function credentials(): array
    {
        return [
            'right password' => [Mailpit::AUTH_PASSWORD, 235],
            'wrong password' => ['not-the-password', 535],
        ];
    }

    #[DataProvider('credentials')]
    public function testTheStarttlsInstanceAuthenticatesOverVerifiedTls(string $password, int $expectedCode): void
    {
        $this->connect('tcp', Mailpit::starttls());
        $this->command('EHLO pidgey.test', 250);
        $this->command('STARTTLS', 220);

        $this->assertTrue(stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT));

        $this->command('EHLO pidgey.test', 250);
        $this->command($this->authPlain($password), $expectedCode);
    }

    #[DataProvider('credentials')]
    public function testTheSslInstanceAuthenticatesOverVerifiedTls(string $password, int $expectedCode): void
    {
        $this->connect('ssl', Mailpit::ssl());
        $this->command('EHLO pidgey.test', 250);
        $this->command($this->authPlain($password), $expectedCode);
    }

    public function testAccessorsExposeTheParsedKey(): void
    {
        $mailpit = $this->withKey('TEST_MAILPIT_PLAIN', 'mail.example:2025:9025', Mailpit::plain(...));

        $this->assertSame('mail.example', $mailpit->host());
        $this->assertSame(2025, $mailpit->smtpPort());
        $this->assertSame(9025, $mailpit->apiPort());
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function invalidKeys(): array
    {
        return [
            'unset' => [null],
            'missing api port' => ['mailpit-plain:2025'],
            'non-numeric port' => ['mailpit-plain:smtp:9025'],
        ];
    }

    #[DataProvider('invalidKeys')]
    public function testAMissingOrMalformedKeyFailsNamingTheKey(?string $value): void
    {
        try {
            $this->withKey('TEST_MAILPIT_PLAIN', $value, Mailpit::plain(...));
        } catch (AssertionFailedError $failure) {
            $this->assertStringContainsString('TEST_MAILPIT_PLAIN', $failure->getMessage());

            return;
        }

        $this->fail('Mailpit::plain() did not fail.');
    }

    /**
     * Runs $build with $key set to $value (unset when null), restoring the key afterwards.
     *
     * @param callable(): Mailpit $build
     */
    private function withKey(string $key, ?string $value, callable $build): Mailpit
    {
        $saved = getenv($key);
        putenv($value === null ? $key : "{$key}={$value}");

        try {
            return $build();
        } finally {
            putenv($saved === false ? $key : "{$key}={$saved}");
        }
    }

    private function connect(string $scheme, Mailpit $mailpit): void
    {
        $caFile = Env::get('SMTP_CA_FILE');

        if ($caFile === null) {
            $this->fail('Missing test environment variable SMTP_CA_FILE.');
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'cafile' => $caFile,
                'peer_name' => $mailpit->host(),
            ],
        ]);
        $address = "{$scheme}://{$mailpit->host()}:{$mailpit->smtpPort()}";

        $stream = stream_socket_client(
            $address,
            $errorCode,
            $errorMessage,
            self::TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($stream === false) {
            $this->fail("Could not connect to {$address}: [{$errorCode}] {$errorMessage}");
        }

        stream_set_timeout($stream, self::TIMEOUT_SECONDS);
        $this->stream = $stream;
        $this->assertReply(220, 'greeting');
    }

    private function command(string $line, int $expectedCode): void
    {
        fwrite($this->stream, $line . "\r\n");
        $this->assertReply($expectedCode, strtok($line, " \r\n") ?: $line);
    }

    private function assertReply(int $expectedCode, string $context): void
    {
        $reply = '';

        do {
            $line = fgets($this->stream);

            if ($line === false) {
                $this->fail("No SMTP reply to {$context}; got so far: {$reply}");
            }

            $reply .= $line;
        } while (strlen($line) >= 4 && $line[3] === '-');

        $this->assertSame($expectedCode, (int) substr($line, 0, 3), "SMTP reply to {$context}: {$reply}");
    }

    private function authPlain(string $password): string
    {
        return 'AUTH PLAIN ' . base64_encode("\0" . Mailpit::AUTH_USER . "\0" . $password);
    }
}
