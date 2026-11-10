<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Handler\HashRedirectHandler;
use Pidgey\Http\Handler\HealthHandler;
use Pidgey\Http\Handler\LoginHandler;
use Pidgey\Http\Handler\RegisterHandler;
use Pidgey\Http\Handler\SmtpConnectionActivateHandler;
use Pidgey\Http\Handler\SmtpConnectionDeactivateHandler;
use Pidgey\Http\Handler\SmtpConnectionHandler;
use Pidgey\Http\Handler\SmtpConnectionsHandler;
use Pidgey\Http\Handler\StaticFileHandler;
use Pidgey\Http\OriginCheck;
use Pidgey\Http\Request;
use Pidgey\Http\Router;

/**
 * Routes-table test: every registered endpoint resolves to its handler, through its own path.
 */
#[CoversNothing]
final class RoutesTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = require __DIR__ . '/../../../config/routes.php';
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function endpoints(): array
    {
        return [
            'GET /' => ['GET', '/', StaticFileHandler::class, '/'],
            'GET /assets/x.js' => ['GET', '/assets/x.js', StaticFileHandler::class, '/assets/*'],
            'GET /health.json' => ['GET', '/health.json', HealthHandler::class, '/health.json'],
            'POST /auth/register.json' => [
                'POST',
                '/auth/register.json',
                RegisterHandler::class,
                '/auth/register.json',
            ],
            'POST /auth/login.json' => ['POST', '/auth/login.json', LoginHandler::class, '/auth/login.json'],
            'GET /smtp-connections.json' => [
                'GET',
                '/smtp-connections.json',
                SmtpConnectionsHandler::class,
                '/smtp-connections.json',
            ],
            'POST /smtp-connections.json' => [
                'POST',
                '/smtp-connections.json',
                SmtpConnectionsHandler::class,
                '/smtp-connections.json',
            ],
            'GET /smtp-connections/12.json' => [
                'GET',
                '/smtp-connections/12.json',
                SmtpConnectionHandler::class,
                '/smtp-connections/:id.json',
            ],
            'PATCH /smtp-connections/12.json' => [
                'PATCH',
                '/smtp-connections/12.json',
                SmtpConnectionHandler::class,
                '/smtp-connections/:id.json',
            ],
            'DELETE /smtp-connections/12.json' => [
                'DELETE',
                '/smtp-connections/12.json',
                SmtpConnectionHandler::class,
                '/smtp-connections/:id.json',
            ],
            'GET with a malformed id' => [
                'GET',
                '/smtp-connections/abc.json',
                SmtpConnectionHandler::class,
                '/smtp-connections/:id.json',
            ],
            'POST /smtp-connections/12/activate.json' => [
                'POST',
                '/smtp-connections/12/activate.json',
                SmtpConnectionActivateHandler::class,
                '/smtp-connections/:id/activate.json',
            ],
            'POST /smtp-connections/12/deactivate.json' => [
                'POST',
                '/smtp-connections/12/deactivate.json',
                SmtpConnectionDeactivateHandler::class,
                '/smtp-connections/:id/deactivate.json',
            ],
        ];
    }

    #[DataProvider('endpoints')]
    public function testEndpointResolvesToItsHandler(
        string $method,
        string $path,
        string $handler,
        string $pattern,
    ): void {
        $match = $this->router->match(new Request(['method' => $method, 'uri' => $path]));

        $this->assertNotNull($match);
        $this->assertSame($handler, $match->handlerClass);
        $this->assertSame($pattern, $match->pattern);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fallbackPaths(): array
    {
        return [
            'deep link' => ['/foo'],
            'bare /v1' => ['/v1'],
        ];
    }

    #[DataProvider('fallbackPaths')]
    public function testGetFallbackRedirectsToTheHashRoute(string $path): void
    {
        $match = $this->router->match(new Request(['method' => 'GET', 'uri' => $path]));

        $this->assertNotNull($match);
        $this->assertSame(HashRedirectHandler::class, $match->handlerClass);
        $this->assertSame(Router::FALLBACK_PATTERN, $match->pattern);
    }

    public function testFrontendRoutesComeBeforeTheFallback(): void
    {
        $match = $this->router->match(new Request(['method' => 'GET', 'uri' => '/']));

        $this->assertNotNull($match);
        $this->assertSame(StaticFileHandler::class, $match->handlerClass);
    }

    public function testOriginCheckIsTheOnlyGlobalMiddleware(): void
    {
        $this->assertSame([OriginCheck::class], $this->router->globalMiddlewares());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unknownRequests(): array
    {
        return [
            'unknown json' => ['GET', '/nope.json'],
            'favicon' => ['GET', '/favicon.ico'],
            'index.php' => ['GET', '/index.php'],
            'v1 API path' => ['GET', '/v1/messages'],
            'HEAD on the index' => ['HEAD', '/'],
            'non-GET deep link' => ['POST', '/foo'],
            'GET on the login endpoint' => ['GET', '/auth/login.json'],
            'PATCH on the register endpoint' => ['PATCH', '/auth/register.json'],
            'PUT on a connection' => ['PUT', '/smtp-connections/12.json'],
            'DELETE on the list' => ['DELETE', '/smtp-connections.json'],
            'POST on a connection' => ['POST', '/smtp-connections/12.json'],
            'GET on activate' => ['GET', '/smtp-connections/12/activate.json'],
            'POST on test (not yet routed)' => ['POST', '/smtp-connections/12/test.json'],
        ];
    }

    #[DataProvider('unknownRequests')]
    public function testUnknownRequestsDoNotMatch(string $method, string $path): void
    {
        $this->assertNull($this->router->match(new Request(['method' => $method, 'uri' => $path])));
    }
}
