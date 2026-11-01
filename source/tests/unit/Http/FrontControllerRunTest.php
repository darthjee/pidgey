<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\FrontController;

/**
 * FrontController::run() reads the PHP globals and the real route file, then echoes the body.
 */
#[CoversClass(FrontController::class)]
final class FrontControllerRunTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSendsTheHealthCheck(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health.json';

        $this->expectOutputString('{"status":"ok"}');

        FrontController::run();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSendsTheNotFoundEnvelope(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $this->expectOutputRegex('/^\{"error":\{"code":"NOT_FOUND","message":"Cannot GET \/"\},"statusCode":404,/');

        FrontController::run();
    }
}
