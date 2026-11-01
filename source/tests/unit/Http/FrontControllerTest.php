<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\FrontController;
use Pidgey\Http\Handler\HealthHandler;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\NotFoundException;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\Route;
use Pidgey\Http\RouteMatch;
use Pidgey\Http\Router;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\WallClock;
use Pidgey\Tests\Support\FirstTraceMiddleware;
use Pidgey\Tests\Support\ParamsHandler;
use Pidgey\Tests\Support\SecondTraceMiddleware;
use Pidgey\Tests\Support\ThrowingHandler;

#[CoversClass(FrontController::class)]
#[UsesClass(BaseMiddleware::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(HealthHandler::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(Response::class)]
#[UsesClass(Route::class)]
#[UsesClass(RouteMatch::class)]
#[UsesClass(Router::class)]
#[UsesClass(SystemWallClock::class)]
final class FrontControllerTest extends TestCase
{
    public const TIMESTAMP = '2026-10-25T12:00:00.000Z';

    /** @var list<string> */
    private array $logged = [];

    private FrontController $controller;

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
        $formatter = new ErrorFormatter($clock, function (string $line): void {
            $this->logged[] = $line;
        });

        $this->controller = new FrontController($router, $formatter);
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
        $this->assertSame(['Content-Type: application/json'], $response->headers());
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

    public function testRouteParamsReachTheHandler(): void
    {
        $this->assertSame('{"id":"42"}', $this->handle('GET', '/items/42.json')->body());
    }

    public function testRunsGlobalMiddlewaresBeforeRouteMiddlewares(): void
    {
        $response = $this->handle('GET', '/items/1.json');

        $this->assertSame(
            ['Content-Type: application/json', 'X-Trace: FirstTraceMiddleware', 'X-Trace: SecondTraceMiddleware'],
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

    public function testDefaultsToTheStandardErrorFormatter(): void
    {
        $response = (new FrontController(new Router()))->handle(new Request(['method' => 'GET', 'uri' => '/']));

        $this->assertSame(404, $response->status());
    }

    private function handle(string $method, string $uri): Response
    {
        return $this->controller->handle(new Request(['method' => $method, 'uri' => $uri]));
    }
}
