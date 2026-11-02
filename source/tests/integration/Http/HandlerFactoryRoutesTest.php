<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\AdminCheck;
use Pidgey\Auth\Gate;
use Pidgey\Container;
use Pidgey\Http\HandlerFactory;
use Pidgey\Http\Middleware;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Router;

/**
 * Builds every routed handler and middleware with the real test container
 * (request-handler/handlers.md#testing): catches a missing build() override and a non-middleware class.
 */
#[CoversNothing]
final class HandlerFactoryRoutesTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>}> Route name => [handler class, global then route middlewares].
     */
    public static function routedClasses(): array
    {
        /** @var Router $router */
        $router = require __DIR__ . '/../../../config/routes.php';
        $global = $router->globalMiddlewares();
        $cases = [];

        foreach ($router->routes() as $route) {
            $cases[$route->method() . ' ' . $route->pattern()] = [
                $route->handlerClass(),
                [...$global, ...$route->middlewares()],
            ];
        }

        $fallback = $router->fallbackHandlerClass();

        if ($fallback !== null) {
            $cases['GET fallback'] = [$fallback, $global];
        }

        return $cases;
    }

    /**
     * @param list<string> $middlewareClasses
     */
    #[DataProvider('routedClasses')]
    public function testBuildsTheHandlerAndMiddlewaresOfEveryRoute(string $handlerClass, array $middlewareClasses): void
    {
        $factory = new HandlerFactory(new Container());

        $handler = $factory->handler($handlerClass);
        $middlewares = $factory->middlewares($middlewareClasses);

        $this->assertInstanceOf($handlerClass, $handler);
        $this->assertInstanceOf(RequestHandler::class, $handler);
        $this->assertSame($middlewareClasses, array_map(static fn (Middleware $m): string => $m::class, $middlewares));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function authMiddlewares(): array
    {
        return [
            'Gate' => [Gate::class],
            'AdminCheck' => [AdminCheck::class],
        ];
    }

    #[DataProvider('authMiddlewares')]
    public function testBuildsTheAuthMiddlewaresWithTheRealContainer(string $class): void
    {
        [$middleware] = (new HandlerFactory(new Container()))->middlewares([$class]);

        $this->assertInstanceOf($class, $middleware);
    }
}
