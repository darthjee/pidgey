<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Request;
use Pidgey\Http\Route;
use Pidgey\Http\RouteMatch;
use Pidgey\Tests\Support\StubHandler;

#[CoversClass(Route::class)]
#[UsesClass(Request::class)]
#[UsesClass(RouteMatch::class)]
final class RouteTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function compilations(): array
    {
        return [
            'literal' => ['/health.json', '#^/health\.json$#'],
            'param before suffix' => ['/smtp-connections/:id.json', '#^/smtp\-connections/(?<id>[^/]+)\.json$#'],
            'param in the middle' => [
                '/smtp-connections/:id/test.json',
                '#^/smtp\-connections/(?<id>[^/]+)/test\.json$#',
            ],
            'two params' => ['/a/:org_id/b/:Id2.json', '#^/a/(?<org_id>[^/]+)/b/(?<Id2>[^/]+)\.json$#'],
            'no .json' => ['/v1/messages', '#^/v1/messages$#'],
            'hash quoted' => ['/a#b.json', '#^/a\#b\.json$#'],
            'prefix' => ['/assets/*', '#^/assets/.*$#'],
            'prefix with param' => ['/a/:id/*', '#^/a/(?<id>[^/]+)/.*$#'],
        ];
    }

    #[DataProvider('compilations')]
    public function testCompilesThePattern(string $pattern, string $regex): void
    {
        $this->assertSame($regex, (new Route('GET', $pattern, StubHandler::class))->regex());
    }

    public function testExposesMethodAndPattern(): void
    {
        $route = new Route('GET', '/a/:id.json', StubHandler::class);

        $this->assertSame('GET', $route->method());
        $this->assertSame('/a/:id.json', $route->pattern());
    }

    public function testNormalizesParamNames(): void
    {
        $route = new Route('GET', '/a/:id/b/:x.json', StubHandler::class);

        $this->assertSame('/a/:/b/:.json', $route->normalizedPattern());
    }

    public function testRejectsARepeatedParamName(): void
    {
        $this->expectException(LogicException::class);

        new Route('GET', '/a/:id/b/:id.json', StubHandler::class);
    }

    public function testCapturesDecodedParams(): void
    {
        $route = new Route('GET', '/a/:id/b/:name.json', StubHandler::class, ['M']);
        $match = $route->match(new Request(['method' => 'GET', 'uri' => '/a/42/b/caf%C3%A9%20x.json?q=1']));

        $this->assertNotNull($match);
        $this->assertSame(['id' => '42', 'name' => 'café x'], $match->params);
        $this->assertSame('/a/:id/b/:name.json', $match->pattern);
        $this->assertSame(StubHandler::class, $match->handlerClass);
        $this->assertSame(['M'], $match->middlewares);
    }

    public function testLiteralRouteHasNoParams(): void
    {
        $match = (new Route('GET', '/health.json', StubHandler::class))->match(
            new Request(['method' => 'GET', 'uri' => '/health.json']),
        );

        $this->assertNotNull($match);
        $this->assertSame([], $match->params);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function misses(): array
    {
        return [
            'wrong method' => ['POST', '/a/1.json'],
            'trailing slash' => ['GET', '/a/1.json/'],
            'case' => ['GET', '/A/1.json'],
            'empty param' => ['GET', '/a/.json'],
            'slash in param' => ['GET', '/a/1/2.json'],
            'suffix' => ['GET', '/a/1.jsonx'],
        ];
    }

    #[DataProvider('misses')]
    public function testDoesNotMatch(string $method, string $uri): void
    {
        $route = new Route('GET', '/a/:id.json', StubHandler::class);

        $this->assertNull($route->match(new Request(['method' => $method, 'uri' => $uri])));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function prefixHits(): array
    {
        return [
            'file' => ['/assets/x.js'],
            'nested' => ['/assets/fonts/x.woff2'],
            'bare prefix' => ['/assets/'],
            'query ignored' => ['/assets/x.js?v=1'],
        ];
    }

    #[DataProvider('prefixHits')]
    public function testPrefixRouteMatchesPathsUnderThePrefix(string $uri): void
    {
        $route = new Route('GET', '/assets/*', StubHandler::class);
        $match = $route->match(new Request(['method' => 'GET', 'uri' => $uri]));

        $this->assertNotNull($match);
        $this->assertSame([], $match->params);
        $this->assertSame('/assets/*', $match->pattern);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function prefixMisses(): array
    {
        return [
            'no trailing slash' => ['GET', '/assets'],
            'longer segment' => ['GET', '/assetsX'],
            'longer segment with slash' => ['GET', '/assetsX/a.js'],
            'case' => ['GET', '/Assets/x.js'],
            'wrong method' => ['POST', '/assets/x.js'],
            'HEAD' => ['HEAD', '/assets/x.js'],
        ];
    }

    #[DataProvider('prefixMisses')]
    public function testPrefixRouteDoesNotMatch(string $method, string $uri): void
    {
        $route = new Route('GET', '/assets/*', StubHandler::class);

        $this->assertNull($route->match(new Request(['method' => $method, 'uri' => $uri])));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function misplacedStars(): array
    {
        return [
            'star after a literal' => ['/a*'],
            'star mid-pattern' => ['/a/*/b'],
            'star followed by text' => ['/*x'],
            'two stars' => ['/a/*/*'],
            'double star' => ['/a/**'],
        ];
    }

    #[DataProvider('misplacedStars')]
    public function testRejectsAMisplacedStar(string $pattern): void
    {
        $this->expectException(LogicException::class);

        new Route('GET', $pattern, StubHandler::class);
    }

    public function testNormalizedPatternKeepsTheStar(): void
    {
        $this->assertSame('/a/:/*', (new Route('GET', '/a/:id/*', StubHandler::class))->normalizedPattern());
    }
}
