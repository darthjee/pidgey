<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Http\Handler\SendMessageHandler;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\HostResolver;
use Pidgey\Tests\Support\Mailpit;
use Pidgey\Tests\Support\SendMessageEndpointTestCase;
use RuntimeException;

/**
 * `POST /v1/messages` when the SMTP server refuses or fails: the `SMTP_*` envelopes.
 */
#[CoversClass(SendMessageHandler::class)]
final class SendMessageFailureTest extends SendMessageEndpointTestCase
{
    public function testATemporaryFailureAnswers503WithTheServerReply(): void
    {
        $account = $this->account(connected: false);
        $this->scriptedConnection($account['organizationId'], [['reply' => "421 4.3.2 busy\r\n"]]);

        $response = $this->post($account['token']);

        $this->assertError(
            $response,
            503,
            'SMTP_TEMPORARY_FAILURE',
            'The SMTP server is temporarily unavailable; try again later',
        );
        $this->assertSame(['code' => 421, 'message' => '4.3.2 busy'], self::json($response)['error']['smtp']);
        $this->assertSame('SMTP_TEMPORARY_FAILURE', $this->attempts()[0]['outcome']);
        $this->assertSame($account['tokenId'], $this->attempts()[0]['tokenId']);
    }

    public function testARejectedRecipientAnswers502WithTheServerReply(): void
    {
        $account = $this->account(connected: false);
        $this->scriptedConnection($account['organizationId'], self::untilRecipient("550 5.1.1 Unknown\r\n"));

        $response = $this->post($account['token']);

        $this->assertError($response, 502, 'SMTP_REJECTED', 'The SMTP server rejected the recipient');
        $this->assertSame(['code' => 550, 'message' => '5.1.1 Unknown'], self::json($response)['error']['smtp']);
    }

    public function testAnInvalidReplyAnswers502WithoutTheServerText(): void
    {
        $account = $this->account(connected: false);
        $this->scriptedConnection($account['organizationId'], self::untilRecipient("250-ok\r\n251 mixed\r\n"));

        $response = $this->post($account['token']);

        $this->assertError($response, 502, 'SMTP_PROTOCOL_ERROR', 'The SMTP server sent an invalid reply');
        $this->assertArrayNotHasKey('smtp', self::json($response)['error']);
    }

    public function testASilentServerAnswers504(): void
    {
        $account = $this->account(connected: false);
        $this->scriptedConnection($account['organizationId'], [['delayMs' => 3000, 'reply' => "220 late\r\n"]]);

        $response = $this->post($account['token']);

        $this->assertError($response, 504, 'SMTP_TIMEOUT', 'The SMTP server timed out');
    }

    public function testAnUnreachableServerAnswers502(): void
    {
        $account = $this->account(connected: false);
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($listener);
        $name = (string) stream_socket_get_name($listener, false);
        fclose($listener);
        $this->connect($account['organizationId'], '127.0.0.1', (int) substr($name, (int) strrpos($name, ':') + 1));

        $response = $this->post($account['token']);

        $this->assertError($response, 502, 'SMTP_CONNECTION_FAILED', 'Could not connect to the SMTP server');
    }

    public function testRefusedCredentialsAnswer502WithoutLeakingThePassword(): void
    {
        $account = $this->account(connected: false);
        $starttls = Mailpit::starttls();
        $starttls->clear();
        $id = $this->fixtures->smtpConnection(
            organizationId: $account['organizationId'],
            host: $starttls->host(),
            port: $starttls->smtpPort(),
            encryption: 'tls',
            username: Mailpit::AUTH_USER,
            active: true,
        );
        $password = 'wrong-canary-password-0123';
        $cipher = new PasswordCipher(new EncryptionKeys(self::ENCRYPTION_KEY));
        $this->pdo->prepare('UPDATE smtp_connections SET password = :password WHERE id = :id')
            ->execute(['password' => $cipher->encrypt($password, $account['organizationId'], $id), 'id' => $id]);

        $response = $this->post($account['token']);

        $this->assertError($response, 502, 'SMTP_AUTH_FAILED', 'The SMTP server rejected the credentials');
        $this->assertSame(535, self::json($response)['error']['smtp']['code']);
        $this->assertStringNotContainsString($password, $response->body());
        $this->log->assertNotLogged($password);
        $this->formatterLog->assertNotLogged($password);
    }

    public function testAnUnexpectedErrorAnswers500(): void
    {
        $account = $this->account();
        $resolver = $this->createStub(HostResolver::class);
        $resolver->method('resolve')->willThrowException(new RuntimeException('resolver exploded'));

        $response = $this->request(
            'POST',
            ['Authorization' => 'Bearer ' . $account['token']],
            hostPolicy: new HostPolicy($resolver, true),
        );

        $this->assertError($response, 500, 'INTERNAL_ERROR', 'Internal server error');
        $this->assertStringNotContainsString('resolver exploded', $response->body());
        $this->assertSame([], $this->keyRows());
    }
}
