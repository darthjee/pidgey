<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use Pidgey\Http\Handler\ApiTokenHandler;
use Pidgey\Http\OperatorLog;
use Pidgey\Tests\Support\SmtpConnectionEndpointTestCase;

/**
 * `DELETE /api-tokens/:id.json` through the real routes.
 */
#[CoversClass(ApiTokenHandler::class)]
final class ApiTokenHandlerTest extends SmtpConnectionEndpointTestCase
{
    public function testRevokesATokenAndIsIdempotent(): void
    {
        $user = $this->account();
        $id = $this->fixtures->apiToken(organizationId: $user->organizationId);

        $first = $this->send($user, 'DELETE', "/api-tokens/{$id}.json");

        $this->assertSame(204, $first->status());
        $this->assertSame('', $first->body());
        $revokedAt = $this->row('api_tokens', 'id', $id)['revoked_at'];
        $this->assertNotNull($revokedAt);
        $this->assertOperatorLogged(OperatorLog::API_TOKEN_REVOKE, $user, $id);

        $second = $this->send($user, 'DELETE', "/api-tokens/{$id}.json");

        $this->assertSame(204, $second->status());
        $this->assertSame($revokedAt, $this->row('api_tokens', 'id', $id)['revoked_at']);
        $this->assertCount(1, $this->operatorLines());
    }

    public function testAForeignOrUnknownIdAnswers404(): void
    {
        $user = $this->account();
        $foreign = $this->fixtures->apiToken();

        foreach ([$foreign, $foreign + 1000000] as $id) {
            $response = $this->send($user, 'DELETE', "/api-tokens/{$id}.json");

            $this->assertError($response, 404, 'NOT_FOUND', 'API token not found');
        }

        $this->assertNull($this->row('api_tokens', 'id', $foreign)['revoked_at']);
        $this->assertSame([], $this->operatorLines());
    }

    public function testAMalformedIdAnswers400(): void
    {
        $user = $this->account();

        foreach (['abc', '0'] as $id) {
            $this->assertError($this->send($user, 'DELETE', "/api-tokens/{$id}.json"), 400, 'BAD_REQUEST');
        }
    }

    public function testAnonymousRequestsAreRejected(): void
    {
        $this->assertSame(401, $this->send(null, 'DELETE', '/api-tokens/1.json')->status());
    }
}
