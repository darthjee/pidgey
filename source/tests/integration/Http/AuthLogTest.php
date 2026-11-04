<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use Pidgey\Http\Response;
use Pidgey\Http\Router;
use Pidgey\Tests\Support\SessionEndpointTestCase;

/**
 * End-to-end register and login through config/routes.php: the origin check applies, and no
 * password, digest, refresh token or access token ever reaches a log, on success or failure.
 *
 * Both the error formatter's logger and PHP's `error_log` (redirected to a temporary file) are
 * captured.
 */
#[CoversNothing]
final class AuthLogTest extends SessionEndpointTestCase
{
    private Router $router;

    private string $errorLog;

    private string|false $previousErrorLog;

    /** A valid register password generated per test, so no password literal is hardcoded. */
    private string $password;

    /** @var list<string> Secrets seen in responses and in the database during the test. */
    private array $secrets = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->password = 'pw-' . bin2hex(random_bytes(12));
        $this->secrets = [$this->password];
        $this->router = require __DIR__ . '/../../../config/routes.php';
        $this->errorLog = (string) tempnam(sys_get_temp_dir(), 'pidgey-auth-log-');
        $this->previousErrorLog = ini_set('error_log', $this->errorLog);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog === false ? '' : $this->previousErrorLog);
        @unlink($this->errorLog);

        parent::tearDown();
    }

    public function testACrossSiteRegisterIsRejectedBeforeTheHandler(): void
    {
        $response = $this->post($this->router, '/auth/register.json', $this->register(), [
            'Sec-Fetch-Site' => 'cross-site',
            'Origin' => 'https://evil.example',
        ]);

        $this->assertSame(403, $response->status());
        $this->assertSame('FORBIDDEN', self::json($response)['error']['code']);
        $this->assertSame(0, $this->rowCount('users'));
        $this->assertNothingSecretWasLogged();
    }

    public function testACrossSiteLoginIsRejectedBeforeTheHandler(): void
    {
        $response = $this->post(
            $this->router,
            '/auth/login.json',
            ['username' => 'bob', 'password' => $this->password],
            ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example'],
        );

        $this->assertSame(403, $response->status());
        $this->assertSame('FORBIDDEN', self::json($response)['error']['code']);
        $this->assertNothingSecretWasLogged();
    }

    public function testRegisterThenLoginWithSameOriginRequests(): void
    {
        $sameOrigin = ['Sec-Fetch-Site' => 'same-origin'];

        $registered = $this->post($this->router, '/auth/register.json', $this->register(), $sameOrigin);
        $this->remember($registered);
        $loggedIn = $this->post(
            $this->router,
            '/auth/login.json',
            ['username' => 'bob', 'password' => $this->password],
            $sameOrigin,
        );
        $this->remember($loggedIn);

        $this->assertSame(201, $registered->status());
        $this->assertSame(201, $loggedIn->status());
        $this->assertNothingSecretWasLogged();
    }

    public function testFailuresLogNoSecret(): void
    {
        $this->remember($this->post($this->router, '/auth/register.json', $this->register()));
        $this->secrets[] = (string) $this->row('users', 'username', 'bob')['password_digest'];
        $this->pdo->exec("UPDATE users SET status = 'denied'");

        $register = '/auth/register.json';
        $login = '/auth/login.json';
        $responses = [
            $this->post($this->router, $register, $this->register()),
            $this->post($this->router, $register, [...$this->register(), 'password' => "a\0" . $this->password]),
            $this->post($this->router, $login, ['username' => 'bob', 'password' => $this->password . 'x']),
            $this->post($this->router, $login, ['username' => 'nobody', 'password' => $this->password]),
            $this->post($this->router, $login, ['username' => 'bob', 'password' => $this->password]),
        ];
        $statuses = array_map(static fn (Response $response): int => $response->status(), $responses);

        $this->assertSame([409, 400, 401, 401, 403], $statuses);
        $this->assertNothingSecretWasLogged();
    }

    public function testAnUnexpectedErrorLogsNoSecret(): void
    {
        putenv('REFRESH_TOKEN_TTL=soon');

        $register = $this->post($this->router, '/auth/register.json', $this->register());
        $login = $this->post($this->router, '/auth/login.json', ['username' => 'bob', 'password' => $this->password]);

        $this->assertSame(500, $register->status());
        $this->assertSame(500, $login->status());
        $this->assertNotSame([], $this->logged);
        $this->assertNothingSecretWasLogged();
    }

    /**
     * @return array<string, string> A valid register body using the generated password.
     */
    private function register(): array
    {
        return [
            'username' => 'bob',
            'email' => 'bob@example.com',
            'password' => $this->password,
            'organizationName' => "Bob's Shop",
        ];
    }

    /**
     * Records the secrets a response carries: the refresh token, the JWT and the stored digest.
     */
    private function remember(Response $response): void
    {
        $this->secrets[] = (string) self::json($response)['refreshToken'];

        foreach (self::cookies($response) as $line) {
            preg_match('/access_token=([^;]+);/', $line, $match);
            $this->secrets[] = $match[1];
        }

        $this->secrets[] = (string) $this->row('users', 'username', 'bob')['password_digest'];
    }

    private function assertNothingSecretWasLogged(): void
    {
        $output = implode("\n", $this->logged) . "\n" . (string) file_get_contents($this->errorLog);

        foreach (array_unique($this->secrets) as $secret) {
            $this->assertNotSame('', $secret);
            $this->assertStringNotContainsString($secret, $output);
        }
    }
}
