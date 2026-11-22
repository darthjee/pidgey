<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Http\Handler\SendMessageHandler;
use Pidgey\Http\Request;
use Pidgey\Tests\Support\SendMessageEndpointTestCase;

/**
 * `POST /v1/messages` through the real routes, delivering to Mailpit: success, the guards and
 * their order, the payload and the connection. The failures of the SMTP server and the
 * `Idempotency-Key` have their own test classes.
 */
#[CoversClass(SendMessageHandler::class)]
final class SendMessageHandlerTest extends SendMessageEndpointTestCase
{
    public function testItSendsTheMessageAndAnswers200WithTheServerReply(): void
    {
        $account = $this->account();

        $response = $this->post($account['token'], [
            'to' => 'user@example.com',
            'subject' => 'Résumé',
            'text' => 'Hi!',
            'html' => '<p>Hi!</p>',
        ]);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertContains('Content-Type: application/json', $response->headers());
        $this->assertNull(self::replayed($response));
        $body = self::json($response);
        $this->assertSame('sent', $body['status']);
        $this->assertSame(250, $body['smtp']['code']);
        $this->assertIsString($body['smtp']['message']);
        $this->assertSame(['status', 'smtp'], array_keys($body));
        $this->assertSame(['code', 'message'], array_keys($body['smtp']));

        $messages = $this->mailpit->messages();
        $this->assertCount(1, $messages);
        $message = $this->mailpit->message((string) $messages[0]['ID']);
        $this->assertSame('Résumé', $message['Subject']);
        $this->assertSame('user@example.com', $message['To'][0]['Address']);
        $this->assertSame('sender@pidgey.test', $message['From']['Address']);
    }

    public function testTheAttemptIsLoggedWithTheTokenAndNotTheBody(): void
    {
        $account = $this->account();

        $this->post($account['token'], ['to' => 'user@example.com', 'subject' => 'Hello', 'text' => 'secret body']);

        $attempts = $this->attempts();
        $this->assertCount(1, $attempts);
        $this->assertSame($account['tokenId'], $attempts[0]['tokenId']);
        $this->assertSame($account['organizationId'], $attempts[0]['organizationId']);
        $this->assertSame('sent', $attempts[0]['outcome']);
        $this->assertArrayNotHasKey('userId', $attempts[0]);
        $this->log->assertNotLogged('secret body');
        $this->log->assertNotLogged($account['token']);
        $this->formatterLog->assertNotLogged($account['token']);
    }

    public function testTheTokenSendsThroughItsOwnOrganizationsConnectionOnly(): void
    {
        $account = $this->account();
        $other = $this->account(connected: false);
        $this->connect($other['organizationId'], '127.0.0.1', 1);

        $response = $this->post($account['token']);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertSame(1, $this->sentCount());
    }

    public function testOnlyTheActiveConnectionIsUsed(): void
    {
        $account = $this->account(connected: false);
        $this->fixtures->smtpConnection(organizationId: $account['organizationId'], host: '127.0.0.1', port: 1);
        $this->connect($account['organizationId'], $this->mailpit->host(), $this->mailpit->smtpPort());

        $response = $this->post($account['token']);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertSame(1, $this->sentCount());
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function badCredentials(): array
    {
        return [
            'missing header' => [[]],
            'basic scheme' => [['Authorization' => 'Basic dXNlcjpwYXNz']],
            'empty token' => [['Authorization' => 'Bearer ']],
            'unknown token' => [['Authorization' => 'Bearer pdg_unknown']],
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('badCredentials')]
    public function testEveryRejectedTokenAnswersTheSame401(array $headers): void
    {
        $revoked = $this->account();
        $this->pdo->exec('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE id = ' . $revoked['tokenId']);
        $unapproved = $this->account();
        $this->pdo->exec(
            "UPDATE users SET status = 'pending' WHERE organization_id = " . $unapproved['organizationId'],
        );

        $reference = $this->post(null, headers: $headers);
        $answers = [
            $reference,
            $this->post($revoked['token']),
            $this->post($unapproved['token']),
        ];

        foreach ($answers as $answer) {
            $this->assertError($answer, 401, 'UNAUTHORIZED', 'Invalid or revoked API token');
            $this->assertSame(self::withoutTimestamp($reference), self::withoutTimestamp($answer));
        }

        $this->assertSame(0, $this->sentCount());
        $this->assertSame([], $this->attempts());
    }

    public function testATokenOfAnOrganizationWithoutAnApprovedUserIsNotRevoked(): void
    {
        $account = $this->account();
        $this->pdo->exec("UPDATE users SET status = 'denied' WHERE organization_id = " . $account['organizationId']);

        $response = $this->post($account['token']);

        $this->assertError($response, 401, 'UNAUTHORIZED');
        $row = $this->pdo->query('SELECT revoked_at, last_used_at FROM api_tokens')->fetch();
        $this->assertNull($row['revoked_at']);
        $this->assertNull($row['last_used_at']);

        $this->pdo->exec("UPDATE users SET status = 'approved' WHERE organization_id = " . $account['organizationId']);

        $this->assertSame(200, $this->post($account['token'])->status());
    }

    public function testAnAccessTokenCookieAloneIsA401(): void
    {
        $account = $this->account();

        $response = $this->request('POST', [], cookies: ['access_token' => $account['token']]);

        $this->assertError($response, 401, 'UNAUTHORIZED');
    }

    public function testAValidTokenSetsLastUsedAt(): void
    {
        $account = $this->account();

        $this->post($account['token']);

        $this->assertNotNull($this->pdo->query('SELECT last_used_at FROM api_tokens')->fetchColumn());
    }

    public function testATokenWithoutTheSendScopeAnswers403AndStillCountsAsUsed(): void
    {
        $account = $this->account(scopes: []);

        $response = $this->post($account['token']);

        $this->assertError($response, 403, 'FORBIDDEN');
        $this->assertSame(0, $this->sentCount());
        $this->assertNotNull($this->pdo->query('SELECT last_used_at FROM api_tokens')->fetchColumn());
    }

    public function testTheGuardsRunInOrder401Then403Then400(): void
    {
        $account = $this->account(scopes: []);
        $invalid = ['to' => 'nope'];

        $this->assertSame(401, $this->post(null, $invalid)->status());
        $this->assertSame(403, $this->post($account['token'], $invalid)->status());

        $allowed = $this->account();

        $this->assertSame(400, $this->post($allowed['token'], $invalid)->status());
    }

    public function testACrossSiteBrowserRequestIsRejectedBeforeAuthentication(): void
    {
        $account = $this->account();
        putenv('ALLOWED_ORIGINS=https://app.example.com');

        $response = $this->post($account['token'], headers: ['Origin' => 'https://evil.example.com']);

        $this->assertError($response, 403, 'FORBIDDEN', 'Cross-site request rejected');
        $this->assertSame(0, $this->sentCount());
    }

    public function testASubjectOfExactly255CharactersIsAccepted(): void
    {
        $account = $this->account();

        $payload = ['to' => 'a@b.co', 'subject' => str_repeat('é', 255), 'text' => 'x'];

        $response = $this->post($account['token'], $payload);

        $this->assertSame(200, $response->status(), $response->body());
    }

    public function testASubjectOf256CharactersAnswers400(): void
    {
        $account = $this->account();

        $payload = ['to' => 'a@b.co', 'subject' => str_repeat('é', 256), 'text' => 'x'];

        $response = $this->post($account['token'], $payload);

        $this->assertError(
            $response,
            400,
            'VALIDATION_FAILED',
            null,
            ['subject must be shorter than or equal to 255 characters'],
        );
        $this->assertSame(0, $this->sentCount());
    }

    public function testAControlCharacterInTheSubjectAnswers400(): void
    {
        $account = $this->account();

        $response = $this->post($account['token'], ['to' => 'a@b.co', 'subject' => "a\r\nBcc: x@y.zz", 'text' => 'x']);

        $this->assertError($response, 400, 'VALIDATION_FAILED', null, ['subject must not contain control characters']);
    }

    public function testAPayloadWithoutABodyAnswers400(): void
    {
        $account = $this->account();

        $response = $this->post($account['token'], ['to' => 'a@b.co', 'subject' => 's']);

        $this->assertError($response, 400, 'VALIDATION_FAILED', null, ['text or html is required']);
    }

    public function testABodyOver1MiBAnswers413(): void
    {
        $account = $this->account();
        $payload = ['to' => 'a@b.co', 'subject' => 's', 'text' => str_repeat('x', Request::MAX_BODY_BYTES)];

        $response = $this->post($account['token'], $payload);

        $this->assertError($response, 413, 'HTTP_413', 'Content Too Large');
        $this->assertSame(0, $this->sentCount());
    }

    public function testAMalformedBodyAnswers400(): void
    {
        $account = $this->account();

        $this->assertError($this->post($account['token'], '{not json'), 400, 'BAD_REQUEST');
        $this->assertError($this->post($account['token'], '[1,2]'), 400, 'BAD_REQUEST');
    }

    public function testAnotherContentTypeAnswers415(): void
    {
        $account = $this->account();

        $response = $this->post($account['token'], 'to=a', ['Content-Type' => 'text/plain']);

        $this->assertError($response, 415, 'HTTP_415', 'Unsupported Media Type');
    }

    public function testWithoutAnActiveConnectionItAnswers409(): void
    {
        $account = $this->account(connected: false);
        $this->fixtures->smtpConnection(organizationId: $account['organizationId'], active: false);

        $response = $this->post($account['token']);

        $this->assertError(
            $response,
            409,
            'NO_SMTP_CONNECTION',
            'The organization has no active SMTP connection',
        );
        $this->assertSame([], $this->attempts());
    }

    public function testSendTimeFailuresAnswer409(): void
    {
        $account = $this->account(connected: false);
        $id = $this->fixtures->smtpConnection(
            organizationId: $account['organizationId'],
            host: $this->mailpit->host(),
            port: $this->mailpit->smtpPort(),
            username: 'mailer',
            active: true,
        );
        $this->pdo->prepare('UPDATE smtp_connections SET password = :password WHERE id = :id')
            ->execute(['password' => 'not-a-valid-ciphertext', 'id' => $id]);

        $insecure = $this->post($account['token']);
        $this->pdo->exec("UPDATE smtp_connections SET encryption = 'tls'");
        $unreadable = $this->post($account['token']);

        $this->assertError($insecure, 409, 'SMTP_INSECURE_CREDENTIALS');
        $this->assertError($unreadable, 409, 'SMTP_PASSWORD_UNREADABLE');
        $this->assertSame(0, $this->sentCount());
    }

    public function testAHostThatIsNotAllowedAnswers409(): void
    {
        $account = $this->account();
        putenv('SMTP_ALLOW_PRIVATE_HOSTS=false');

        $response = $this->post($account['token']);

        $this->assertError($response, 409, 'SMTP_HOST_NOT_ALLOWED');
        $this->assertSame(0, $this->sentCount());
    }

    public function testGetOnTheRouteStillAnswersAJson404(): void
    {
        $account = $this->account();

        $response = $this->request('GET', ['Authorization' => 'Bearer ' . $account['token']], []);

        $this->assertError($response, 404, 'NOT_FOUND');
    }
}
