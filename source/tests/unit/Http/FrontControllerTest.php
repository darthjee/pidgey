<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Container;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\Cors;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\FrontController;
use Pidgey\Http\HandlerFactory;
use Pidgey\Http\Handler\HealthHandler;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\NotFoundException;
use Pidgey\Http\OriginCheck;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\Route;
use Pidgey\Http\RouteMatch;
use Pidgey\Http\Router;
use Pidgey\Http\TrustedOrigins;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\Env;
use Pidgey\Infra\OriginParser;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\WallClock;
use Pidgey\Tests\Support\ContainerHandler;
use Pidgey\Tests\Support\FirstTraceMiddleware;
use Pidgey\Tests\Support\ParamsHandler;
use Pidgey\Tests\Support\SecondTraceMiddleware;
use Pidgey\Tests\Support\ThrowingHandler;

#[CoversClass(FrontController::class)]
#[UsesClass(BadRequestException::class)]
#[UsesClass(BaseMiddleware::class)]
#[UsesClass(Container::class)]
#[UsesClass(Cors::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(Env::class)]
#[UsesClass(ForbiddenException::class)]
#[UsesClass(HandlerFactory::class)]
#[UsesClass(HealthHandler::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(OriginCheck::class)]
#[UsesClass(OriginParser::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(Response::class)]
#[UsesClass(Route::class)]
#[UsesClass(RouteMatch::class)]
#[UsesClass(Router::class)]
#[UsesClass(SystemWallClock::class)]
#[UsesClass(TrustedOrigins::class)]
final class FrontControllerTest extends TestCase
{
    public const TIMESTAMP = '2026-10-25T12:00:00.000Z';

    private const TRUSTED = 'https://admin.test';

    private const CORS_HEADERS = [
        'Vary: Origin',
        'Access-Control-Allow-Origin: ' . self::TRUSTED,
        'Access-Control-Allow-Credentials: true',
    ];

    /** @var list<string> */
    private array $logged = [];

    private FrontController $controller;

    private ErrorFormatter $formatter;

    private Cors $cors;

    protected function setUp(): void
    {
        $router = new Router([FirstTraceMiddleware::class]);
        $router->get('/health.json', HealthHandler::class);
        $router->get('/items/:id.json', ParamsHandler::class, [SecondTraceMiddleware::class]);
        $router->get('/fail/:kind.json', ThrowingHandler::class);

        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable(FrontControllerTest::TIMESTAMP);
            }
        };
        $this->formatter = new ErrorFormatter($clock, function (string $line): void {
            $this->logged[] = $line;
        });

        $this->cors = new Cors(new TrustedOrigins([self::TRUSTED]));
        $this->controller = new FrontController($router, $this->formatter, cors: $this->cors);
    }

    public function testAnswersTheHealthCheck(): void
    {
        $response = $this->handle('GET', '/health.json');

        $this->assertSame(200, $response->status());
        $this->assertSame('{"status":"ok"}', $response->body());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function notFound(): array
    {
        return [
            'root' => ['GET', '/', 'Cannot GET /'],
            'unknown path' => ['GET', '/does-not-exist.json', 'Cannot GET /does-not-exist.json'],
            'wrong method' => ['POST', '/health.json', 'Cannot POST /health.json'],
            'HEAD' => ['HEAD', '/health.json', 'Cannot HEAD /health.json'],
            'OPTIONS' => ['OPTIONS', '/health.json', 'Cannot OPTIONS /health.json'],
            'trailing slash' => ['GET', '/health.json/', 'Cannot GET /health.json/'],
            'case' => ['GET', '/Health.json', 'Cannot GET /Health.json'],
            'decoded path, no query' => ['GET', '/a%20b.json?x=1', 'Cannot GET /a b.json'],
        ];
    }

    #[DataProvider('notFound')]
    public function testUnmatchedRequestsAnswer404(string $method, string $uri, string $message): void
    {
        $response = $this->handle($method, $uri);

        $this->assertSame(404, $response->status());
        $this->assertSame(['Content-Type: application/json', 'Vary: Origin'], $response->headers());
        $this->assertSame(
            [
                'error' => ['code' => 'NOT_FOUND', 'message' => $message],
                'statusCode' => 404,
                'timestamp' => self::TIMESTAMP,
            ],
            json_decode($response->body(), true),
        );
        $this->assertSame([], $this->logged);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function notFoundDespiteFallback(): array
    {
        return [
            'non-GET' => ['POST', '/foo', 'Cannot POST /foo'],
            'unknown json' => ['GET', '/x.json', 'Cannot GET /x.json'],
        ];
    }

    #[DataProvider('notFoundDespiteFallback')]
    public function testAFallbackKeepsThe404ForOtherRequests(string $method, string $uri, string $message): void
    {
        $router = new Router();
        $router->get('/health.json', HealthHandler::class);
        $router->fallback(HealthHandler::class);
        $controller = new FrontController($router, $this->formatter);

        $response = $controller->handle(new Request(['method' => $method, 'uri' => $uri]));

        $this->assertSame(404, $response->status());
        $this->assertSame(
            ['code' => 'NOT_FOUND', 'message' => $message],
            json_decode($response->body(), true)['error'],
        );
        $this->assertSame(200, $controller->handle(new Request(['method' => 'GET', 'uri' => '/foo']))->status());
    }

    public function testRouteParamsReachTheHandler(): void
    {
        $this->assertSame('{"id":"42"}', $this->handle('GET', '/items/42.json')->body());
    }

    public function testRunsGlobalMiddlewaresBeforeRouteMiddlewares(): void
    {
        $response = $this->handle('GET', '/items/1.json');

        $this->assertSame(
            [
                'Content-Type: application/json',
                'X-Trace: FirstTraceMiddleware',
                'X-Trace: SecondTraceMiddleware',
                'Vary: Origin',
            ],
            $response->headers(),
        );
    }

    public function testAnHttpExceptionGivesItsEnvelope(): void
    {
        $response = $this->handle('GET', '/fail/http.json');

        $this->assertSame(409, $response->status());
        $this->assertSame(
            '{"error":{"code":"USERNAME_TAKEN","message":"Username already in use"},'
            . '"statusCode":409,"timestamp":"' . self::TIMESTAMP . '"}',
            $response->body(),
        );
    }

    public function testANulByteInThePathAnswersTheJson400(): void
    {
        $response = $this->handle('GET', '/health%00.json');

        $this->assertSame(400, $response->status());
        $this->assertSame(
            '{"error":{"code":"BAD_REQUEST","message":"Bad Request"},'
            . '"statusCode":400,"timestamp":"' . self::TIMESTAMP . '"}',
            $response->body(),
        );
        $this->assertSame([], $this->logged);
    }

    public function testAnyOtherThrowableGives500WithoutLeaking(): void
    {
        $response = $this->handle('GET', '/fail/other.json');

        $this->assertSame(500, $response->status());
        $this->assertSame(
            '{"error":{"code":"INTERNAL_ERROR","message":"Internal server error"},'
            . '"statusCode":500,"timestamp":"' . self::TIMESTAMP . '"}',
            $response->body(),
        );
        $this->assertCount(1, $this->logged);
        $this->assertStringContainsString(ThrowingHandler::SECRET, $this->logged[0]);
    }

    public function testOriginCheckRejectsACrossSitePost(): void
    {
        $router = new Router([OriginCheck::class]);
        $router->get('/health.json', HealthHandler::class);
        $router->post('/health.json', HealthHandler::class);
        $controller = new FrontController($router, $this->formatter);
        $headers = ['Origin' => 'https://evil.example', 'Host' => 'pidgey.example'];

        $post = $controller->handle(new Request(['method' => 'POST', 'uri' => '/health.json', 'headers' => $headers]));
        $get = $controller->handle(new Request(['method' => 'GET', 'uri' => '/health.json', 'headers' => $headers]));

        $this->assertSame(403, $post->status());
        $this->assertSame(
            '{"error":{"code":"FORBIDDEN","message":"Cross-site request rejected"},'
            . '"statusCode":403,"timestamp":"' . self::TIMESTAMP . '"}',
            $post->body(),
        );
        $this->assertSame(200, $get->status());
        $this->assertSame('{"status":"ok"}', $get->body());
        $this->assertSame([], $this->logged);
    }

    public function testTheHealthCheckOpensNoDatabaseConnection(): void
    {
        $router = new Router([OriginCheck::class]);
        $router->get('/health.json', HealthHandler::class);
        $connections = $this->createMock(ConnectionFactory::class);
        $connections->expects($this->never())->method('create');
        $connections->expects($this->never())->method('createServer');
        $controller = new FrontController(
            $router,
            $this->formatter,
            static fn (): Container => new Container($connections),
        );

        $response = $controller->handle(new Request(['method' => 'GET', 'uri' => '/health.json']));

        $this->assertSame(200, $response->status());
        $this->assertSame('{"status":"ok"}', $response->body());
    }

    public function testBuildsOneFreshContainerPerRequest(): void
    {
        $router = new Router();
        $router->get('/container.json', ContainerHandler::class);
        $built = [];
        $controller = new FrontController($router, $this->formatter, static function () use (&$built): Container {
            return $built[] = new Container();
        });

        $controller->handle(new Request(['method' => 'GET', 'uri' => '/container.json']));
        $controller->handle(new Request(['method' => 'GET', 'uri' => '/container.json']));

        $this->assertCount(2, $built);
        $this->assertNotSame($built[0], $built[1]);
    }

    public function testDefaultsToTheStandardErrorFormatter(): void
    {
        $response = (new FrontController(new Router()))->handle(new Request(['method' => 'GET', 'uri' => '/']));

        $this->assertSame(404, $response->status());
    }

    public function testAnswersAPreflightBeforeRouting(): void
    {
        $built = 0;
        $controller = new FrontController(
            new Router([FirstTraceMiddleware::class]),
            $this->formatter,
            static function () use (&$built): Container {
                $built++;

                return new Container();
            },
            $this->cors,
        );

        $response = $controller->handle(new Request([
            'method' => 'OPTIONS',
            'uri' => '/v1/not-routed.json',
            'headers' => ['Origin' => self::TRUSTED, 'Access-Control-Request-Method' => 'POST'],
        ]));

        $this->assertSame(204, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame(
            [
                'Access-Control-Allow-Methods: GET, POST, PATCH, DELETE',
                'Access-Control-Allow-Headers: Content-Type',
                ...self::CORS_HEADERS,
            ],
            $response->headers(),
        );
        $this->assertSame(0, $built);
        $this->assertSame([], $this->logged);
    }

    public function testAnswersAnUntrustedPreflightWithoutCorsHeaders(): void
    {
        $response = $this->handle(
            'OPTIONS',
            '/health.json',
            ['Origin' => 'https://evil.test', 'Access-Control-Request-Method' => 'POST'],
        );

        $this->assertSame(204, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame(['Vary: Origin'], $response->headers());
    }

    public function testANonPreflightOptionsIsRouted(): void
    {
        $response = $this->handle('OPTIONS', '/health.json', ['Origin' => self::TRUSTED]);

        $this->assertSame(404, $response->status());
        $this->assertSame(
            ['code' => 'NOT_FOUND', 'message' => 'Cannot OPTIONS /health.json'],
            json_decode($response->body(), true)['error'],
        );
        $this->assertSame(['Content-Type: application/json', ...self::CORS_HEADERS], $response->headers());
    }

    /**
     * @return array<string, array{string, string, integer, list<string>}>
     */
    public static function finalResponses(): array
    {
        return [
            'health check' => [
                'GET',
                '/health.json',
                200,
                ['Content-Type: application/json', 'X-Trace: FirstTraceMiddleware'],
            ],
            'routing 404' => ['GET', '/nope.json', 404, ['Content-Type: application/json']],
            'http exception' => ['GET', '/fail/http.json', 409, ['Content-Type: application/json']],
            'internal error' => ['GET', '/fail/other.json', 500, ['Content-Type: application/json']],
            'with middlewares' => ['GET', '/items/7.json', 200, [
                'Content-Type: application/json',
                'X-Trace: FirstTraceMiddleware',
                'X-Trace: SecondTraceMiddleware',
            ]],
        ];
    }

    /**
     * @param list<string> $headers
     */
    #[DataProvider('finalResponses')]
    public function testAddsTheCorsHeadersToEveryFinalResponse(
        string $method,
        string $uri,
        int $status,
        array $headers,
    ): void {
        $plain = $this->handle($method, $uri);
        $trusted = $this->handle($method, $uri, ['Origin' => self::TRUSTED]);
        $untrusted = $this->handle($method, $uri, ['Origin' => 'https://evil.test']);

        $this->assertSame($status, $trusted->status());
        $this->assertSame($plain->status(), $trusted->status());
        $this->assertSame($plain->body(), $trusted->body());
        $this->assertSame([...$headers, ...self::CORS_HEADERS], $trusted->headers());
        $this->assertSame($plain->status(), $untrusted->status());
        $this->assertSame($plain->body(), $untrusted->body());
        $this->assertSame([...$headers, 'Vary: Origin'], $untrusted->headers());
    }

    public function testAddsOnlyVaryToARequestRejectedByTheOriginCheck(): void
    {
        $router = new Router([OriginCheck::class]);
        $router->post('/health.json', HealthHandler::class);
        $controller = new FrontController($router, $this->formatter, cors: $this->cors);

        $response = $controller->handle(new Request([
            'method' => 'POST',
            'uri' => '/health.json',
            'headers' => ['Origin' => 'https://evil.test', 'Sec-Fetch-Site' => 'cross-site'],
        ]));

        $this->assertSame(403, $response->status());
        $this->assertSame('FORBIDDEN', json_decode($response->body(), true)['error']['code']);
        $this->assertSame(['Content-Type: application/json', 'Vary: Origin'], $response->headers());
    }

    /**
     * @param array<string, string> $headers
     */
    private function handle(string $method, string $uri, array $headers = []): Response
    {
        return $this->controller->handle(new Request(['method' => $method, 'uri' => $uri, 'headers' => $headers]));
    }
}
