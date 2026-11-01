<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Handler\HealthHandler;
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
     * @return array<string, array{string, string, string}>
     */
    public static function endpoints(): array
    {
        return [
            'GET /health.json' => ['GET', '/health.json', HealthHandler::class],
        ];
    }

    #[DataProvider('endpoints')]
    public function testEndpointResolvesToItsHandler(string $method, string $path, string $handler): void
    {
        $match = $this->router->match(new Request(['method' => $method, 'uri' => $path]));

        $this->assertNotNull($match);
        $this->assertSame($handler, $match->handlerClass);
        $this->assertSame($path, $match->pattern);
    }

    public function testHasNoGlobalMiddlewaresYet(): void
    {
        $this->assertSame([], $this->router->globalMiddlewares());
    }

    public function testUnknownPathsDoNotMatch(): void
    {
        $this->assertNull($this->router->match(new Request(['method' => 'GET', 'uri' => '/'])));
    }
}
