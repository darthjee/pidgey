<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Handler\HealthHandler;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;

#[CoversClass(HealthHandler::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(Response::class)]
final class HealthHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function requestProvider(): iterable
    {
        foreach (['GET', 'POST', 'HEAD', 'OPTIONS', 'DELETE'] as $method) {
            foreach (['/', '/health.json', '/anything/else?x=1'] as $uri) {
                yield "{$method} {$uri}" => [$method, $uri];
            }
        }
    }

    #[DataProvider('requestProvider')]
    public function testAnswersOk(string $method, string $uri): void
    {
        $request = new ProcessingRequest(new Request(['method' => $method, 'uri' => $uri]));

        $response = (new HealthHandler())->handle($request, []);

        $this->assertSame(200, $response->status());
        $this->assertSame(['Content-Type: application/json'], $response->headers());
        $this->assertSame('{"status":"ok"}', $response->body());
    }
}
