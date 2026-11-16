<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use Pidgey\Http\Handler\SmtpConnectionTestHandler;
use Pidgey\Smtp\HostPolicy;
use Pidgey\Smtp\HostResolver;
use Pidgey\Tests\Support\Mailpit;
use Pidgey\Tests\Support\SmtpConnectionTestEndpointTestCase;
use RuntimeException;

/**
 * `POST /smtp-connections/:id/test.json` through the real routes, delivering to Mailpit.
 */
#[CoversClass(SmtpConnectionTestHandler::class)]
final class SmtpConnectionTestFailureTest extends SmtpConnectionTestEndpointTestCase
{
    public function testSendTimeFailuresAreAnswered409AndCounted(): void
    {
        $user = $this->member('caller@example.com');
        $organizationId = $user->organizationId;
        $plain = Mailpit::plain();
        $starttls = Mailpit::starttls();
        $insecure = $this->connectionWithCredentials($organizationId, host: $plain->host(), encryption: 'none');
        $unreadable = $this->connectionWithCredentials($organizationId, host: $starttls->host());
        $this->pdo->prepare('UPDATE smtp_connections SET port = :port WHERE id = :id')
            ->execute(['port' => $plain->smtpPort(), 'id' => $insecure]);
        $this->pdo->prepare('UPDATE smtp_connections SET port = :port WHERE id = :id')
            ->execute(['port' => $starttls->smtpPort(), 'id' => $unreadable]);
        $this->setStoredPassword($unreadable, 'not-a-valid-ciphertext');

        $first = $this->send($user, 'POST', self::path($insecure));
        $second = $this->send($user, 'POST', self::path($unreadable));

        $this->assertSame(409, $first->status(), $first->body());
        $this->assertSame('SMTP_INSECURE_CREDENTIALS', self::json($first)['error']['code']);
        $this->assertSame(409, $second->status(), $second->body());
        $this->assertSame('SMTP_PASSWORD_UNREADABLE', self::json($second)['error']['code']);
        $this->assertSame(2, (int) $this->limitRow($user)['attempts']);
        $this->assertNull($this->limitRow($user)['busy_until']);
        $this->assertSame(
            ['SMTP_INSECURE_CREDENTIALS', 'SMTP_PASSWORD_UNREADABLE'],
            array_map(static fn (array $line): string => $line[1]['outcome'], $this->testLines()),
        );
    }

    public function testAHostThatIsNotAllowedIsAnswered409(): void
    {
        putenv('SMTP_ALLOW_PRIVATE_HOSTS=false');
        $user = $this->member('caller@example.com');
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId, host: '127.0.0.1', port: 2025);

        $response = $this->send($user, 'POST', self::path($id));

        $this->assertError($response, 409, 'SMTP_HOST_NOT_ALLOWED');
        $this->assertSame(1, (int) $this->limitRow($user)['attempts']);
    }

    public function testASmtpFailureCarriesTheServerReplyAndReleasesTheLease(): void
    {
        $user = $this->member('caller@example.com');
        $starttls = Mailpit::starttls();
        $id = $this->connectionWithCredentials(
            $user->organizationId,
            username: Mailpit::AUTH_USER,
            password: 'wrong-' . self::$canary,
            host: $starttls->host(),
        );
        $this->pdo->prepare('UPDATE smtp_connections SET port = :port WHERE id = :id')
            ->execute(['port' => $starttls->smtpPort(), 'id' => $id]);

        $response = $this->send($user, 'POST', self::path($id));

        $this->assertError($response, 502, 'SMTP_AUTH_FAILED');
        $this->assertSame(535, self::json($response)['error']['smtp']['code']);
        $this->assertNull($this->limitRow($user)['busy_until']);
        $this->assertSame('SMTP_AUTH_FAILED', $this->testLines()[0][1]['outcome']);
    }

    public function testAnUnexpectedErrorReleasesTheLeaseAndWritesNoOperatorLine(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);
        $resolver = $this->createStub(HostResolver::class);
        $resolver->method('resolve')->willThrowException(new RuntimeException('resolver exploded'));

        $response = $this->sendWithHostPolicy($user, $id, new HostPolicy($resolver, true));

        $this->assertSame(500, $response->status());
        $this->assertNull($this->limitRow($user)['busy_until']);
        $this->assertSame([], $this->testLines());
    }
}
