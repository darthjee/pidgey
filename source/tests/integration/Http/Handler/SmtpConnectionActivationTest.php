<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Auth\Gate;
use Pidgey\Http\Handler\SmtpConnectionActivateHandler;
use Pidgey\Http\Handler\SmtpConnectionDeactivateHandler;
use Pidgey\Http\Handler\SmtpConnectionStateHandler;
use Pidgey\Http\OperatorLog;
use Pidgey\Http\PathId;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Tests\Support\SmtpConnectionEndpointTestCase;

/**
 * `POST /smtp-connections/:id/activate.json` and `…/deactivate.json` through the real routes.
 */
#[CoversClass(SmtpConnectionActivateHandler::class)]
#[CoversClass(SmtpConnectionDeactivateHandler::class)]
#[CoversClass(SmtpConnectionStateHandler::class)]
#[CoversClass(OrganizationRepository::class)]
#[CoversClass(SmtpConnectionRepository::class)]
final class SmtpConnectionActivationTest extends SmtpConnectionEndpointTestCase
{
    private const NOT_FOUND = 'SMTP connection not found';

    public function testActivateMakesTheConnectionTheOnlyActiveOne(): void
    {
        $user = $this->account();
        $organizationId = $user->organizationId;
        $previous = $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
        $id = $this->connectionWithCredentials($organizationId);
        $foreign = $this->fixtures->smtpConnection(organizationId: $this->account()->organizationId, active: true);

        $response = $this->send($user, 'POST', self::path($id, 'activate'));

        $this->assertSame(200, $response->status(), $response->body());
        $item = self::item($response);
        $this->assertSame($id, $item['id']);
        $this->assertTrue($item['active']);
        $this->assertTrue($item['hasPassword']);
        $this->assertSame([$id], $this->activeIds($organizationId));
        $this->assertSame('0', (string) $this->row('smtp_connections', 'id', $previous)['active']);
        $this->assertSame('1', (string) $this->row('smtp_connections', 'id', $foreign)['active']);
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_ACTIVATE, $user, $id);
    }

    public function testActivateIsIdempotent(): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId, active: true);

        $response = $this->send($user, 'POST', self::path($id, 'activate'));

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertTrue(self::item($response)['active']);
        $this->assertSame([$id], $this->activeIds($user->organizationId));
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_ACTIVATE, $user, $id);
    }

    public function testActivateIgnoresTheBody(): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId);

        $response = $this->send($user, 'POST', self::path($id, 'activate'), ['active' => false, 'name' => 'x']);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertTrue(self::item($response)['active']);
        $this->assertNotSame('x', self::item($response)['name']);
    }

    public function testDeactivateChangesTheTargetOnlyAndIsIdempotent(): void
    {
        $user = $this->account();
        $organizationId = $user->organizationId;
        $id = $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
        $other = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $foreign = $this->fixtures->smtpConnection(organizationId: $this->account()->organizationId, active: true);

        $response = $this->send($user, 'POST', self::path($id, 'deactivate'));

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertSame($id, self::item($response)['id']);
        $this->assertFalse(self::item($response)['active']);
        $this->assertSame([], $this->activeIds($organizationId));
        $this->assertSame('1', (string) $this->row('smtp_connections', 'id', $foreign)['active']);
        $this->assertOperatorLogged(OperatorLog::SMTP_CONNECTION_DEACTIVATE, $user, $id);

        $again = $this->send($user, 'POST', self::path($id, 'deactivate'), ['active' => true]);
        $otherDeactivated = $this->send($user, 'POST', self::path($other, 'deactivate'));

        $this->assertSame(200, $again->status(), $again->body());
        $this->assertFalse(self::item($again)['active']);
        $this->assertSame(200, $otherDeactivated->status(), $otherDeactivated->body());
        $this->assertSame([], $this->activeIds($organizationId));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actions(): array
    {
        return ['activate' => ['activate'], 'deactivate' => ['deactivate']];
    }

    #[DataProvider('actions')]
    public function testAnUnknownOrHugeIdAnswers404(string $action): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId, active: true);

        foreach ([(string) ($id + 1000), '9223372036854775808', '99999999999999999999'] as $raw) {
            $response = $this->send($user, 'POST', "/smtp-connections/{$raw}/{$action}.json");

            $this->assertError($response, 404, 'NOT_FOUND', self::NOT_FOUND);
        }

        $this->assertSame([$id], $this->activeIds($user->organizationId));
        $this->assertSame([], $this->operatorLines());
    }

    #[DataProvider('actions')]
    public function testAnotherOrganizationsConnectionAnswers404(string $action): void
    {
        $owner = $this->account();
        $foreign = $this->fixtures->smtpConnection(
            organizationId: $owner->organizationId,
            active: $action === 'deactivate',
        );
        $before = $this->row('smtp_connections', 'id', $foreign);

        $response = $this->send($this->account(), 'POST', self::path($foreign, $action));

        $this->assertError($response, 404, 'NOT_FOUND', self::NOT_FOUND);
        $this->assertSame($before, $this->row('smtp_connections', 'id', $foreign));
        $this->assertSame([], $this->operatorLines());
    }

    #[DataProvider('actions')]
    public function testAMalformedIdAnswers400(string $action): void
    {
        $user = $this->account();

        foreach (['abc', '0', '007', '-3'] as $raw) {
            $response = $this->send($user, 'POST', "/smtp-connections/{$raw}/{$action}.json");

            $this->assertError($response, 400, 'BAD_REQUEST', PathId::MALFORMED);
        }
    }

    #[DataProvider('actions')]
    public function testTheGateAnswers401BeforeTheId(string $action): void
    {
        $id = $this->fixtures->smtpConnection(organizationId: $this->account()->organizationId);

        foreach ([(string) $id, 'abc', '99999999999999999999'] as $raw) {
            $response = $this->send(null, 'POST', "/smtp-connections/{$raw}/{$action}.json");

            $this->assertError($response, 401, 'UNAUTHORIZED', Gate::UNAUTHORIZED_MESSAGE);
        }

        $this->assertSame('0', (string) $this->row('smtp_connections', 'id', $id)['active']);
    }

    #[DataProvider('actions')]
    public function testACrossSiteCallIsRejected(string $action): void
    {
        $user = $this->account();
        $id = $this->fixtures->smtpConnection(organizationId: $user->organizationId, active: $action === 'deactivate');
        $before = $this->row('smtp_connections', 'id', $id);

        $response = $this->send(
            $user,
            'POST',
            self::path($id, $action),
            [],
            ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example'],
        );

        $this->assertError($response, 403, 'FORBIDDEN', 'Cross-site request rejected');
        $this->assertSame($before, $this->row('smtp_connections', 'id', $id));
    }

    private static function path(int $id, string $action): string
    {
        return "/smtp-connections/{$id}/{$action}.json";
    }
}
