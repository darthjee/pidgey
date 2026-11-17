<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use Pidgey\Http\ApiTokenPayload;
use Pidgey\Http\ApiTokenRules;
use Pidgey\Http\Handler\ApiTokensHandler;
use Pidgey\Http\OperatorLog;
use PHPUnit\Framework\Attributes\CoversClass;
use Pidgey\Auth\ApiTokens;
use Pidgey\Repository\ApiTokenRepository;
use Pidgey\Tests\Support\SmtpConnectionEndpointTestCase;

/**
 * `GET` and `POST /api-tokens.json` through the real routes.
 */
#[CoversClass(ApiTokensHandler::class)]
#[CoversClass(ApiTokenPayload::class)]
#[CoversClass(ApiTokenRules::class)]
#[CoversClass(ApiTokens::class)]
#[CoversClass(ApiTokenRepository::class)]
final class ApiTokensHandlerTest extends SmtpConnectionEndpointTestCase
{
    private const PATH = '/api-tokens.json';

    private const ITEM_KEYS = ['id', 'name', 'tokenPrefix', 'scopes', 'createdAt', 'lastUsedAt', 'revokedAt'];

    public function testAnEmptyListAnswersAnEmptyArray(): void
    {
        $response = $this->send($this->account(), 'GET', self::PATH);

        $this->assertSame(200, $response->status());
        $this->assertSame('{"apiTokens":[]}', $response->body());
    }

    public function testListsOwnTokensInIdOrderIncludingRevoked(): void
    {
        $user = $this->account();
        $first = $this->fixtures->apiToken(organizationId: $user->organizationId, name: 'One');
        $second = $this->fixtures->apiToken(organizationId: $user->organizationId, revokedAt: '2026-03-02 10:00:00');
        $this->fixtures->apiToken();

        $response = $this->send($user, 'GET', self::PATH);

        $this->assertSame(200, $response->status());
        $items = self::json($response)['apiTokens'];
        $this->assertSame([$first, $second], array_column($items, 'id'));
        $this->assertNull($items[0]['revokedAt']);
        $this->assertSame('2026-03-02T10:00:00.000Z', $items[1]['revokedAt']);

        foreach ($items as $item) {
            $this->assertSame(self::ITEM_KEYS, array_keys($item));
        }

        $this->assertSame([], $this->operatorLines());
    }

    public function testCreatesATokenAndReturnsThePlainTokenOnce(): void
    {
        $user = $this->account();

        $response = $this->send($user, 'POST', self::PATH, ['name' => ' Moria ', 'scopes' => ['send', 'send']]);

        $this->assertSame(201, $response->status(), $response->body());
        $item = self::json($response)['apiToken'];
        $this->assertSame([...self::ITEM_KEYS, 'token'], array_keys($item));
        $this->assertSame('Moria', $item['name']);
        $this->assertSame('pdg_', $item['tokenPrefix']);
        $this->assertSame(['send'], $item['scopes']);
        $this->assertMatchesRegularExpression('/^pdg_[0-9a-f]{64}$/', $item['token']);
        $row = $this->row('api_tokens', 'id', $item['id']);
        $this->assertSame(hash('sha256', $item['token']), $row['token_hash']);
        $this->assertSame($user->organizationId, (int) $row['organization_id']);

        $list = $this->send($user, 'GET', self::PATH);
        $this->assertStringNotContainsString($item['token'], $list->body());
        $this->assertStringNotContainsString($row['token_hash'], $list->body());

        $lines = $this->operatorLines();
        $this->assertSame(OperatorLog::API_TOKEN_CREATE, $lines[0][0]);
        $this->assertSame($item['id'], $lines[0][1]['connectionId']);
        $this->log->assertNotLogged($item['token']);
        $this->log->assertNotLogged($row['token_hash']);
    }

    public function testAnInvalidBodyReportsEveryErrorAtOnce(): void
    {
        $response = $this->send($this->account(), 'POST', self::PATH, ['name' => '  ', 'scopes' => []]);

        $this->assertValidationFailed($response, ['name should not be empty', ApiTokenRules::SCOPES_EMPTY]);
        $this->assertSame([], $this->operatorLines());
    }

    public function testAnUnknownScopeIsRejected(): void
    {
        $response = $this->send($this->account(), 'POST', self::PATH, ['name' => 'a', 'scopes' => ['admin']]);

        $this->assertValidationFailed($response, [ApiTokenRules::SCOPES_UNKNOWN]);
    }

    public function testAnonymousRequestsAreRejected(): void
    {
        $this->assertSame(401, $this->send(null, 'GET', self::PATH)->status());
        $this->assertSame(401, $this->send(null, 'POST', self::PATH, ['name' => 'a', 'scopes' => ['send']])->status());
    }
}
