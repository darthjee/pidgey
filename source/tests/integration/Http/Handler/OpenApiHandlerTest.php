<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Http\Handler\OpenApiHandler;
use Pidgey\Tests\Support\SendMessageEndpointTestCase;

/**
 * `GET` and `HEAD /v1/openapi.json` through the real routes and front controller: public, exact
 * match only, and the rest of `/v1` stays guarded.
 */
#[CoversClass(OpenApiHandler::class)]
final class OpenApiHandlerTest extends SendMessageEndpointTestCase
{
    private const DOCUMENT = __DIR__ . '/../../../../openapi/v1.json';

    /**
     * @return array<string, array{string, string}>
     */
    public static function credentials(): array
    {
        return [
            'GET without credentials' => ['GET', 'none'],
            'GET with a valid Bearer' => ['GET', 'valid'],
            'GET with an invalid Bearer' => ['GET', 'invalid'],
            'HEAD without credentials' => ['HEAD', 'none'],
            'HEAD with a valid Bearer' => ['HEAD', 'valid'],
            'HEAD with an invalid Bearer' => ['HEAD', 'invalid'],
        ];
    }

    #[DataProvider('credentials')]
    public function testItIsPublicAndIgnoresCredentials(string $method, string $credential): void
    {
        $account = $this->account();
        $headers = match ($credential) {
            'valid' => ['Authorization' => 'Bearer ' . $account['token']],
            'invalid' => ['Authorization' => 'Bearer pdg_example_invalid'],
            default => [],
        };

        $response = $this->request($method, $headers, '', path: '/v1/openapi.json');

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertContains('Content-Type: application/json', $response->headers());
        $this->assertSame(
            $method === 'GET' ? (string) file_get_contents(self::DOCUMENT) : '',
            $response->body(),
        );
        $this->assertNull($this->lastUsedAt($account['tokenId']));
        $this->assertSame([], $this->attempts());
    }

    public function testNoCacheHeadersAreSent(): void
    {
        $response = $this->request('GET', [], '', path: '/v1/openapi.json');

        foreach ($response->headers() as $line) {
            $this->assertStringStartsNotWith('Cache-Control', $line);
            $this->assertStringStartsNotWith('ETag', $line);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unmatched(): array
    {
        return [
            'trailing slash' => ['GET', '/v1/openapi.json/'],
            'extra suffix' => ['GET', '/v1/openapi.jsonx'],
            'POST' => ['POST', '/v1/openapi.json'],
            'PUT' => ['PUT', '/v1/openapi.json'],
        ];
    }

    #[DataProvider('unmatched')]
    public function testOtherPathsAndMethodsAnswerTheRoutingNotFound(string $method, string $path): void
    {
        $response = $this->request($method, [], '', path: $path);

        $this->assertError($response, 404, 'NOT_FOUND');
    }

    public function testSendingStillNeedsAToken(): void
    {
        $this->assertError($this->post(null), 401, 'UNAUTHORIZED');
    }

    private function lastUsedAt(int $tokenId): ?string
    {
        $statement = $this->pdo->prepare('SELECT last_used_at FROM api_tokens WHERE id = :id');
        $statement->execute(['id' => $tokenId]);
        $value = $statement->fetchColumn();

        return $value === null || $value === false ? null : (string) $value;
    }
}
