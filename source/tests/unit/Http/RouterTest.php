<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Handler\HealthHandler;
use Pidgey\Http\Request;
use Pidgey\Http\Response;
use Pidgey\Http\Route;
use Pidgey\Http\RouteMatch;
use Pidgey\Http\Router;
use Pidgey\Tests\Support\RecordingMiddleware;
use Pidgey\Tests\Support\StubHandler;

#[CoversClass(Router::class)]
#[UsesClass(Request::class)]
#[UsesClass(Route::class)]
#[UsesClass(RouteMatch::class)]
final class RouterTest extends TestCase
{
    public function testGlobalMiddlewaresDefaultToEmpty(): void
    {
        $this->assertSame([], (new Router())->globalMiddlewares());
    }

    public function testReturnsTheGlobalMiddlewaresUnchanged(): void
    {
        $this->assertSame(['B', 'A'], (new Router(['B', 'A']))->globalMiddlewares());
    }

    public function testMatchesARegisteredRoute(): void
    {
        $router = new Router();
        $router->get('/health.json', HealthHandler::class, [RecordingMiddleware::class]);

        $match = $router->match($this->request('GET', '/health.json'));

        $this->assertNotNull($match);
        $this->assertSame(HealthHandler::class, $match->handlerClass);
        $this->assertSame([RecordingMiddleware::class], $match->middlewares);
        $this->assertSame('/health.json', $match->pattern);
    }

    public function testReturnsNullWithoutRoutes(): void
    {
        $this->assertNull((new Router())->match($this->request('GET', '/')));
    }

    public function testCapturesParams(): void
    {
        $router = new Router();
        $router->get('/smtp-connections/:id/test.json', StubHandler::class);

        $match = $router->match($this->request('GET', '/smtp-connections/7/test.json'));

        $this->assertNotNull($match);
        $this->assertSame(['id' => '7'], $match->params);
    }

    public function testFirstMatchWins(): void
    {
        $router = new Router();
        $router->post('/admin/users/search.json', StubHandler::class);
        $router->post('/admin/users/:id.json', HealthHandler::class);

        $this->assertSame(StubHandler::class, $this->handlerFor($router, 'POST', '/admin/users/search.json'));
        $this->assertSame(HealthHandler::class, $this->handlerFor($router, 'POST', '/admin/users/3.json'));
    }

    public function testTriesRoutesInRegistrationOrder(): void
    {
        $router = new Router();
        $router->post('/admin/users/:id.json', HealthHandler::class);
        $router->post('/admin/users/search.json', StubHandler::class);

        $this->assertSame(HealthHandler::class, $this->handlerFor($router, 'POST', '/admin/users/search.json'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function helpers(): array
    {
        return [
            'get' => ['get', 'GET'],
            'post' => ['post', 'POST'],
            'patch' => ['patch', 'PATCH'],
            'delete' => ['delete', 'DELETE'],
        ];
    }

    #[DataProvider('helpers')]
    public function testHelpersRegisterTheirMethod(string $helper, string $method): void
    {
        $router = new Router();
        $router->{$helper}('/a.json', StubHandler::class);

        $this->assertNotNull($router->match($this->request($method, '/a.json')));
        $this->assertNull($router->match($this->request($method === 'GET' ? 'POST' : 'GET', '/a.json')));
    }

    public function testUpperCasesTheMethodAtRegistration(): void
    {
        $router = new Router();
        $router->add('get', '/a.json', StubHandler::class);

        $this->assertNotNull($router->match($this->request('GET', '/a.json')));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function misses(): array
    {
        return [
            'wrong method' => ['POST', '/health.json'],
            'HEAD' => ['HEAD', '/health.json'],
            'OPTIONS' => ['OPTIONS', '/health.json'],
            'root' => ['GET', '/'],
            'trailing slash' => ['GET', '/health.json/'],
            'case' => ['GET', '/Health.json'],
            'unknown path' => ['GET', '/does-not-exist.json'],
        ];
    }

    #[DataProvider('misses')]
    public function testReturnsNullWhenNothingMatches(string $method, string $uri): void
    {
        $router = new Router();
        $router->get('/health.json', HealthHandler::class);

        $this->assertNull($router->match($this->request($method, $uri)));
    }

    public function testIgnoresTheQueryString(): void
    {
        $router = new Router();
        $router->get('/health.json', HealthHandler::class);

        $this->assertNotNull($router->match($this->request('GET', '/health.json?x=1')));
    }

    public function testRejectsADuplicateRoute(): void
    {
        $router = new Router();
        $router->get('/a/:id.json', StubHandler::class);

        $this->expectException(LogicException::class);
        $router->add('get', '/a/:x.json', HealthHandler::class);
    }

    public function testAllowsTheSamePatternWithAnotherMethod(): void
    {
        $router = new Router();
        $router->get('/a/:id.json', StubHandler::class);
        $router->patch('/a/:id.json', HealthHandler::class);

        $this->assertSame(HealthHandler::class, $this->handlerFor($router, 'PATCH', '/a/1.json'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonHandlers(): array
    {
        return [
            'not a handler' => [Response::class],
            'unknown class' => ['Pidgey\\Nope'],
        ];
    }

    #[DataProvider('nonHandlers')]
    public function testRejectsANonHandlerClass(string $class): void
    {
        $this->expectException(LogicException::class);

        (new Router())->get('/a.json', $class);
    }

    public function testRejectsARepeatedParamName(): void
    {
        $this->expectException(LogicException::class);

        (new Router())->get('/a/:id/b/:id.json', StubHandler::class);
    }

    public function testRejectsARouteMiddlewareThatIsGlobal(): void
    {
        $router = new Router([RecordingMiddleware::class]);

        $this->expectException(LogicException::class);
        $router->get('/a.json', StubHandler::class, [RecordingMiddleware::class]);
    }

    private function handlerFor(Router $router, string $method, string $uri): ?string
    {
        return $router->match($this->request($method, $uri))?->handlerClass;
    }

    private function request(string $method, string $uri): Request
    {
        return new Request(['method' => $method, 'uri' => $uri]);
    }
}
