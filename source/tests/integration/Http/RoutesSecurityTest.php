<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\AdminCheck;
use Pidgey\Auth\Gate;
use Pidgey\Http\OriginCheck;
use Pidgey\Http\Router;
use Pidgey\Tests\Support\RouteGuards;
use Pidgey\Tests\Support\StubHandler;

/**
 * Deny-by-default test over config/routes.php: forgetting the gate on a new route fails CI.
 */
#[CoversNothing]
final class RoutesSecurityTest extends TestCase
{
    public function testEveryNonPublicRouteIsGuarded(): void
    {
        $router = require __DIR__ . '/../../../config/routes.php';

        $this->assertSame([], RouteGuards::violations($router));
    }

    public function testEverySmtpConnectionRouteListsTheGate(): void
    {
        /** @var Router $router */
        $router = require __DIR__ . '/../../../config/routes.php';
        $routes = [];

        foreach ($router->routes() as $route) {
            if (str_starts_with($route->pattern(), '/smtp-connections')) {
                $routes[$route->method() . ' ' . $route->pattern()] = $route->middlewares();
            }
        }

        $this->assertSame(
            [
                'GET /smtp-connections.json' => [Gate::class],
                'POST /smtp-connections.json' => [Gate::class],
                'GET /smtp-connections/:id.json' => [Gate::class],
                'PATCH /smtp-connections/:id.json' => [Gate::class],
                'DELETE /smtp-connections/:id.json' => [Gate::class],
                'POST /smtp-connections/:id/activate.json' => [Gate::class],
                'POST /smtp-connections/:id/deactivate.json' => [Gate::class],
                'POST /smtp-connections/:id/test.json' => [Gate::class],
            ],
            $routes,
        );
    }

    public function testTheFallbackIsPublicAndUnguarded(): void
    {
        $router = require __DIR__ . '/../../../config/routes.php';

        $this->assertNotNull($router->fallbackHandlerClass());
        $this->assertNotContains(Gate::class, $router->globalMiddlewares());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function publicRoutes(): array
    {
        return [
            'GET /' => ['GET', '/'],
            'GET /assets/*' => ['GET', '/assets/*'],
            'GET /health.json' => ['GET', '/health.json'],
            'POST /auth/register.json' => ['POST', '/auth/register.json'],
            'POST /auth/login.json' => ['POST', '/auth/login.json'],
            'POST /auth/refresh.json' => ['POST', '/auth/refresh.json'],
            'DELETE /auth/logoff.json' => ['DELETE', '/auth/logoff.json'],
            'POST /auth/status.json' => ['POST', '/auth/status.json'],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function testAllowlistedRoutesNeedNoGuard(string $method, string $pattern): void
    {
        $router = new Router([OriginCheck::class]);
        $router->add($method, $pattern, StubHandler::class);

        $this->assertSame([], RouteGuards::violations($router));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unguardedRoutes(): array
    {
        return [
            'another GET' => ['GET', '/me.json'],
            'POST to a public path' => ['POST', '/health.json'],
            'GET of a public POST path' => ['GET', '/auth/login.json'],
            'another auth endpoint' => ['POST', '/auth/account.json'],
            'POST of the public DELETE path' => ['POST', '/auth/logoff.json'],
            'a sub-path of a public prefix' => ['GET', '/assets/x/*'],
        ];
    }

    #[DataProvider('unguardedRoutes')]
    public function testAnUnguardedRouteFails(string $method, string $pattern): void
    {
        $router = new Router([OriginCheck::class]);
        $router->add($method, $pattern, StubHandler::class);

        $this->assertSame(["{$method} {$pattern}: neither Gate nor ApiTokenAuth"], RouteGuards::violations($router));
    }

    public function testGateOrApiTokenAuthGuardsARoute(): void
    {
        $router = new Router([OriginCheck::class]);
        $router->get('/me.json', StubHandler::class, [Gate::class]);
        $router->post('/v1/messages', StubHandler::class, [RouteGuards::API_TOKEN_AUTH]);
        $router->get('/admin/users.json', StubHandler::class, [Gate::class, AdminCheck::class]);

        $this->assertSame([], RouteGuards::violations($router));
    }

    public function testAdminCheckWithoutGateFails(): void
    {
        $router = new Router();
        $router->get('/admin/users.json', StubHandler::class, [RouteGuards::API_TOKEN_AUTH, AdminCheck::class]);

        $this->assertSame(
            ['GET /admin/users.json: AdminCheck without a Gate before it'],
            RouteGuards::violations($router),
        );
    }

    public function testAdminCheckBeforeGateFails(): void
    {
        $router = new Router();
        $router->get('/admin/users.json', StubHandler::class, [AdminCheck::class, Gate::class]);

        $this->assertSame(
            ['GET /admin/users.json: AdminCheck without a Gate before it'],
            RouteGuards::violations($router),
        );
    }

    public function testAdminCheckAloneFailsBothRules(): void
    {
        $router = new Router();
        $router->get('/admin/users.json', StubHandler::class, [AdminCheck::class]);

        $this->assertSame(
            [
                'GET /admin/users.json: neither Gate nor ApiTokenAuth',
                'GET /admin/users.json: AdminCheck without a Gate before it',
            ],
            RouteGuards::violations($router),
        );
    }

    public function testAGlobalGateGuardsEveryRoute(): void
    {
        $router = new Router([Gate::class]);
        $router->get('/admin/users.json', StubHandler::class, [AdminCheck::class]);

        $this->assertSame([], RouteGuards::violations($router));
    }
}
