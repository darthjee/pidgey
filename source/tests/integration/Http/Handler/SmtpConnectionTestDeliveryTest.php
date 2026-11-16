<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use Pidgey\Http\Handler\SmtpConnectionTestHandler;
use Pidgey\Tests\Support\SmtpConnectionTestEndpointTestCase;

/**
 * `POST /smtp-connections/:id/test.json` through the real routes, delivering to Mailpit.
 */
#[CoversClass(SmtpConnectionTestHandler::class)]
final class SmtpConnectionTestDeliveryTest extends SmtpConnectionTestEndpointTestCase
{
    public function testSendsToTheCallerByDefault(): void
    {
        $user = $this->member('Caller@Example.com');
        $id = $this->plainConnection($user);

        $response = $this->send($user, 'POST', self::path($id));

        $this->assertSame(201, $response->status(), $response->body());
        $body = self::json($response);
        $this->assertSame('sent', $body['status']);
        $this->assertSame(250, $body['smtp']['code']);
        $this->assertNotSame('', $body['smtp']['message']);
        $this->assertSame(['Caller@Example.com'], $this->recipients());
        $this->assertSame('Pidgey test email', $this->mailpit->messages()[0]['Subject']);
        $this->assertNull($this->limitRow($user)['busy_until']);
        $this->assertSame(1, (int) $this->limitRow($user)['attempts']);
    }

    public function testSendsToAnotherApprovedUserOfTheOrganization(): void
    {
        $user = $this->member('caller@example.com');
        $other = $this->member('other@example.com', $user->organizationId);
        $id = $this->plainConnection($user);

        $response = $this->send($user, 'POST', self::path($id), ['to' => $other->email]);

        $this->assertSame(201, $response->status(), $response->body());
        $this->assertSame(['other@example.com'], $this->recipients());
    }

    public function testSendsToTheStoredAddressWhateverTheTypedCase(): void
    {
        $user = $this->member('caller@example.com');
        $this->member('Bob@Example.com', $user->organizationId);
        $id = $this->plainConnection($user);

        $response = $this->send($user, 'POST', self::path($id), ['to' => '  bob@EXAMPLE.com ']);

        $this->assertSame(201, $response->status(), $response->body());
        $this->assertSame(['Bob@Example.com'], $this->recipients());
    }

    public function testAnInactiveConnectionCanBeTested(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user, active: false);

        $this->assertSame(201, $this->send($user, 'POST', self::path($id))->status());
    }

    public function testTheConnectionRowIsUnchanged(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);
        $before = $this->row('smtp_connections', 'id', $id);

        $this->send($user, 'POST', self::path($id));

        $this->assertSame($before, $this->row('smtp_connections', 'id', $id));
    }

    public function testTheLogLinesCarryNoRecipientInTheOperatorLine(): void
    {
        $user = $this->member('caller@example.com');
        $id = $this->plainConnection($user);

        $this->send($user, 'POST', self::path($id));

        $messages = array_map(static fn (array $line): string => $line[0], $this->operatorLines());
        $this->assertSame(['smtp-send-attempt', 'smtp-connection-test'], $messages);
        $lines = $this->operatorLines();
        $this->assertSame(
            [
                'userId' => $user->id,
                'organizationId' => $user->organizationId,
                'connectionId' => $id,
                'outcome' => 'sent',
            ],
            $lines[1][1],
        );
        $this->assertStringContainsString('caller@example.com', (string) json_encode($lines[0][1]));
        $this->assertStringNotContainsString('caller@example.com', (string) json_encode($lines[1][1]));
    }

    public function testAnAnonymousCallerIsUnauthorized(): void
    {
        $this->assertSame(401, $this->send(null, 'POST', self::path(1))->status());
    }
}
