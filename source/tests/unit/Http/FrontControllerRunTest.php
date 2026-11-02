<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\FrontController;
use Pidgey\Infra\ConfigValidator;

/**
 * FrontController::run() reads the PHP globals and the real route file, then echoes the body.
 */
#[CoversClass(FrontController::class)]
final class FrontControllerRunTest extends TestCase
{
    private const SECRET_KEY = 'test-secret-value-not-a-real-secret-front-controller';

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSendsTheHealthCheck(): void
    {
        $this->setValidEnvironment();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health.json';

        $this->expectOutputString('{"status":"ok"}');

        FrontController::run();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSendsTheNotFoundEnvelope(): void
    {
        $this->setValidEnvironment();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/nope.json';

        $this->expectOutputRegex(
            '/^\{"error":\{"code":"NOT_FOUND","message":"Cannot GET \/nope\.json"\},"statusCode":404,/',
        );

        FrontController::run();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRefusesAnInvalidConfiguration(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health.json';
        ini_set('error_log', '/dev/null');

        ob_start();
        FrontController::run(static fn (): array => ['SECRET_KEY: too short', 'DB_HOST: missing']);
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression(
            '/^\{"error":\{"code":"INTERNAL_ERROR","message":"Internal server error"\},"statusCode":500,/',
            $output,
        );
        $this->assertStringNotContainsString('SECRET_KEY', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRefusesTheDevelopmentKeyOutsideDevelopment(): void
    {
        $this->setValidEnvironment();
        putenv('SECRET_KEY=' . ConfigValidator::DEVELOPMENT_SECRET_KEY);
        putenv('PHP_ENV=production');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health.json';
        ini_set('error_log', '/dev/null');

        ob_start();
        FrontController::run();
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression('/^\{"error":\{"code":"INTERNAL_ERROR",/', $output);
        $this->assertStringNotContainsString(ConfigValidator::DEVELOPMENT_SECRET_KEY, $output);
    }

    private function setValidEnvironment(): void
    {
        putenv('SECRET_KEY=' . self::SECRET_KEY);
        putenv('PREVIOUS_SECRET_KEYS');
        putenv('DB_HOST=db.example');
        putenv('DB_NAME=pidgey');
        putenv('DB_USER=pidgey');
    }
}
