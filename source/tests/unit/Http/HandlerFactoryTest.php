<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Container;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\HandlerFactory;
use Pidgey\Http\RequestHandler;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Tests\Support\ContainerHandler;
use Pidgey\Tests\Support\ContainerMiddleware;
use Pidgey\Tests\Support\FirstTraceMiddleware;
use Pidgey\Tests\Support\SecondTraceMiddleware;
use Pidgey\Tests\Support\StubHandler;

#[CoversClass(HandlerFactory::class)]
#[UsesClass(BaseMiddleware::class)]
#[UsesClass(Container::class)]
#[UsesClass(RequestHandler::class)]
final class HandlerFactoryTest extends TestCase
{
    private Container $container;

    private HandlerFactory $factory;

    protected function setUp(): void
    {
        $connections = $this->createMock(ConnectionFactory::class);
        $connections->expects($this->never())->method('create');
        $this->container = new Container($connections);
        $this->factory = new HandlerFactory($this->container);
    }

    public function testBuildsAHandler(): void
    {
        $this->assertInstanceOf(StubHandler::class, $this->factory->handler(StubHandler::class));
    }

    public function testBuildsANewHandlerEachTime(): void
    {
        $this->assertNotSame($this->factory->handler(StubHandler::class), $this->factory->handler(StubHandler::class));
    }

    public function testBuildsMiddlewaresInOrder(): void
    {
        $middlewares = $this->factory->middlewares([
            SecondTraceMiddleware::class,
            FirstTraceMiddleware::class,
            SecondTraceMiddleware::class,
        ]);

        $this->assertSame(
            [SecondTraceMiddleware::class, FirstTraceMiddleware::class, SecondTraceMiddleware::class],
            array_map(static fn (object $middleware): string => $middleware::class, $middlewares),
        );
        $this->assertSame([0, 1, 2], array_keys($middlewares));
    }

    public function testBuildsNoMiddlewareFromAnEmptyList(): void
    {
        $this->assertSame([], $this->factory->middlewares([]));
    }

    public function testAHandlerBuildOverrideReceivesTheContainer(): void
    {
        $handler = $this->factory->handler(ContainerHandler::class);

        $this->assertInstanceOf(ContainerHandler::class, $handler);
        $this->assertSame($this->container, $handler->container);
    }

    public function testAMiddlewareBuildOverrideReceivesTheContainer(): void
    {
        [$middleware] = $this->factory->middlewares([ContainerMiddleware::class]);

        $this->assertInstanceOf(ContainerMiddleware::class, $middleware);
        $this->assertSame($this->container, $middleware->container);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notHandlers(): array
    {
        return [
            'a middleware' => [FirstTraceMiddleware::class],
            'an unrelated class' => [Container::class],
            'the base class itself' => [RequestHandler::class],
            'an unknown class' => ['Pidgey\\DoesNotExist'],
        ];
    }

    #[DataProvider('notHandlers')]
    public function testRejectsAClassThatIsNotAHandler(string $class): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Not a request handler: {$class}");

        $this->factory->handler($class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notMiddlewares(): array
    {
        return [
            'a handler' => [StubHandler::class],
            'an unrelated class' => [Container::class],
            'an unknown class' => ['Pidgey\\DoesNotExist'],
        ];
    }

    #[DataProvider('notMiddlewares')]
    public function testRejectsAClassThatIsNotAMiddleware(string $class): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Not a middleware: {$class}");

        $this->factory->middlewares([FirstTraceMiddleware::class, $class]);
    }
}
