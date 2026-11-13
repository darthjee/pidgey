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
    private const SMTP_KEY = 'test-smtp-encryption-value-not-a-real-key-front-controller';

    private ?string $logFile = null;

    protected function tearDown(): void
    {
        if ($this->logFile !== null && is_file($this->logFile)) {
            unlink($this->logFile);
        }

        $this->logFile = null;
    }

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
        $this->redirectErrorLog();

        ob_start();
        FrontController::run(static fn (): array => ['SECRET_KEY: too short', 'DB_HOST: missing']);
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression(
            '/^\{"error":\{"code":"INTERNAL_ERROR","message":"Internal server error"\},"statusCode":500,/',
            $output,
        );
        $this->assertStringNotContainsString('SECRET_KEY', $output);
        $this->assertStringContainsString(
            'pidgey.ERROR: Unhandled RuntimeException: Invalid configuration: '
            . 'SECRET_KEY: too short, DB_HOST: missing',
            $this->loggedText(),
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRefusesAnInvalidLogLevelAndStillLogsIt(): void
    {
        $this->setValidEnvironment();
        putenv('LOG_LEVEL=verbose');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health.json';
        $this->redirectErrorLog();

        ob_start();
        FrontController::run();
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression(
            '/^\{"error":\{"code":"INTERNAL_ERROR","message":"Internal server error"\},"statusCode":500,/',
            $output,
        );
        $log = $this->loggedText();
        $this->assertMatchesRegularExpression(
            '/pidgey\.ERROR: Unhandled RuntimeException: Invalid configuration: (.*, )?LOG_LEVEL: invalid level/',
            $log,
        );
        $this->assertStringNotContainsString('verbose', $log);
        $this->assertStringNotContainsString('verbose', $output);
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
        $this->redirectErrorLog();

        ob_start();
        FrontController::run();
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression('/^\{"error":\{"code":"INTERNAL_ERROR",/', $output);
        $this->assertStringNotContainsString(ConfigValidator::DEVELOPMENT_SECRET_KEY, $output);
        $log = $this->loggedText();
        $this->assertStringContainsString('pidgey.ERROR: Unhandled RuntimeException: Invalid configuration: ', $log);
        $this->assertStringNotContainsString(ConfigValidator::DEVELOPMENT_SECRET_KEY, $log);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRefusesAMissingSmtpEncryptionKey(): void
    {
        $this->setValidEnvironment();
        putenv('SMTP_ENCRYPTION_KEY');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health.json';
        ini_set('error_log', '/dev/null');

        ob_start();
        FrontController::run();
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression('/^\{"error":\{"code":"INTERNAL_ERROR",/', $output);
        $this->assertStringNotContainsString('SMTP_ENCRYPTION_KEY', $output);
    }

    private function redirectErrorLog(): void
    {
        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'pidgey-run-log-');
        ini_set('error_log', $this->logFile);
    }

    private function loggedText(): string
    {
        return (string) file_get_contents((string) $this->logFile);
    }

    private function setValidEnvironment(): void
    {
        putenv('SECRET_KEY=' . self::SECRET_KEY);
        putenv('PREVIOUS_SECRET_KEYS');
        putenv('SMTP_ENCRYPTION_KEY=' . self::SMTP_KEY);
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS');
        putenv('DB_HOST=db.example');
        putenv('DB_NAME=pidgey');
        putenv('DB_USER=pidgey');
        putenv('LOG_LEVEL');
    }
}
