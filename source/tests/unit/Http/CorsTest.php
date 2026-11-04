<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Cors;
use Pidgey\Http\Request;
use Pidgey\Http\Response;
use Pidgey\Http\TrustedOrigins;
use Pidgey\Infra\Env;

#[CoversClass(Cors::class)]
#[UsesClass(Env::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
#[UsesClass(TrustedOrigins::class)]
final class CorsTest extends TestCase
{
    private const TRUSTED = 'https://admin.test';

    private const VARIABLES = ['ALLOWED_ORIGINS', 'FRONTEND_BASE_URL'];

    /** @var array<string, string|false> */
    private array $saved = [];

    private Cors $cors;

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
            putenv($name);
        }

        $this->cors = new Cors(new TrustedOrigins([self::TRUSTED, 'http://localhost:5173']));
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    public function testAnswersATrustedPreflight(): void
    {
        $response = $this->cors->preflight($this->preflightRequest(self::TRUSTED));

        $this->assertNotNull($response);
        $this->assertSame(204, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame(
            [
                'Access-Control-Allow-Methods: GET, POST, PATCH, DELETE',
                'Access-Control-Allow-Headers: Content-Type',
            ],
            $response->headers(),
        );
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function untrustedPreflights(): array
    {
        return [
            'untrusted origin' => ['https://evil.test', [self::TRUSTED]],
            'null origin' => ['null', [self::TRUSTED, 'null']],
            'empty origin' => ['', [self::TRUSTED]],
            'other port' => ['https://admin.test:8443', [self::TRUSTED]],
            'other scheme' => ['http://admin.test', [self::TRUSTED]],
            'cors off' => [self::TRUSTED, []],
        ];
    }

    /**
     * @param list<string> $trusted
     */
    #[DataProvider('untrustedPreflights')]
    public function testAnswersAnUntrustedPreflightWithoutCorsHeaders(string $origin, array $trusted): void
    {
        $response = (new Cors(new TrustedOrigins($trusted)))->preflight($this->preflightRequest($origin));

        $this->assertNotNull($response);
        $this->assertSame(204, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame([], $response->headers());
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function notPreflights(): array
    {
        return [
            'OPTIONS without Origin' => ['OPTIONS', ['Access-Control-Request-Method' => 'POST']],
            'OPTIONS without request method' => ['OPTIONS', ['Origin' => self::TRUSTED]],
            'OPTIONS without either' => ['OPTIONS', []],
            'GET with both' => ['GET', ['Origin' => self::TRUSTED, 'Access-Control-Request-Method' => 'POST']],
            'POST with both' => ['POST', ['Origin' => self::TRUSTED, 'Access-Control-Request-Method' => 'POST']],
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('notPreflights')]
    public function testIgnoresRequestsThatAreNotPreflights(string $method, array $headers): void
    {
        $request = new Request(['method' => $method, 'uri' => '/v1/auth/login', 'headers' => $headers]);

        $this->assertNull($this->cors->preflight($request));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function trustedOrigins(): array
    {
        return [
            'first' => [self::TRUSTED],
            'with port' => ['http://localhost:5173'],
        ];
    }

    #[DataProvider('trustedOrigins')]
    public function testAddsTheHeadersForATrustedOrigin(string $origin): void
    {
        $response = new Response('{"ok":true}', 401, ['Content-Type: application/json']);

        $result = $this->cors->addHeaders($this->request(['Origin' => $origin]), $response);

        $this->assertSame($response, $result);
        $this->assertSame(401, $result->status());
        $this->assertSame('{"ok":true}', $result->body());
        $this->assertSame(
            [
                'Content-Type: application/json',
                'Vary: Origin',
                'Access-Control-Allow-Origin: ' . $origin,
                'Access-Control-Allow-Credentials: true',
            ],
            $result->headers(),
        );
    }

    /**
     * @return array<string, array{array<string, string>, list<string>}>
     */
    public static function untrustedRequests(): array
    {
        return [
            'untrusted origin' => [['Origin' => 'https://evil.test'], [self::TRUSTED]],
            'no origin' => [[], [self::TRUSTED]],
            'null origin' => [['Origin' => 'null'], [self::TRUSTED, 'null']],
            'empty origin' => [['Origin' => ''], [self::TRUSTED]],
            'other port' => [['Origin' => 'https://admin.test:8443'], [self::TRUSTED]],
            'other scheme' => [['Origin' => 'http://admin.test'], [self::TRUSTED]],
            'trailing slash' => [['Origin' => self::TRUSTED . '/'], [self::TRUSTED]],
            'cors off' => [['Origin' => self::TRUSTED], []],
        ];
    }

    /**
     * @param array<string, string> $headers
     * @param list<string>          $trusted
     */
    #[DataProvider('untrustedRequests')]
    public function testAddsOnlyVaryOtherwise(array $headers, array $trusted): void
    {
        $response = new Response('body', 200, ['Content-Type: text/plain']);

        $result = (new Cors(new TrustedOrigins($trusted)))->addHeaders($this->request($headers), $response);

        $this->assertSame($response, $result);
        $this->assertSame(200, $result->status());
        $this->assertSame('body', $result->body());
        $this->assertSame(['Content-Type: text/plain', 'Vary: Origin'], $result->headers());
    }

    public function testNeverSendsAWildcard(): void
    {
        $cors = new Cors(new TrustedOrigins(['*']));

        $response = $cors->addHeaders($this->request(['Origin' => 'https://evil.test']), new Response());

        $this->assertSame(['Vary: Origin'], $response->headers());
    }

    public function testDefaultsToTheOriginsFromTheEnvironment(): void
    {
        putenv('ALLOWED_ORIGINS=https://env.test');
        $cors = new Cors();

        $trusted = $cors->addHeaders($this->request(['Origin' => 'https://env.test']), new Response());
        $other = $cors->addHeaders($this->request(['Origin' => self::TRUSTED]), new Response());

        $this->assertContains('Access-Control-Allow-Origin: https://env.test', $trusted->headers());
        $this->assertSame(['Vary: Origin'], $other->headers());
    }

    private function preflightRequest(string $origin): Request
    {
        return new Request([
            'method' => 'OPTIONS',
            'uri' => '/v1/auth/login',
            'headers' => ['Origin' => $origin, 'Access-Control-Request-Method' => 'POST'],
        ]);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(array $headers): Request
    {
        return new Request(['method' => 'GET', 'uri' => '/health.json', 'headers' => $headers]);
    }
}
