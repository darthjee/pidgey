<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Http\Response;
use Pidgey\Http\Router;
use Pidgey\Tests\Support\SessionEndpointTestCase;

/**
 * End-to-end session endpoints (register, login, refresh, logoff, status, account) through
 * config/routes.php: the origin check applies, and no password, digest, refresh token, token hash
 * or access token ever reaches a log, on success or failure.
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

        $this->password = self::randomPassword('pw-');
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
        $this->assertNotSame([], $this->formatterLog->records());
        $this->assertNothingSecretWasLogged();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sessionRoutes(): array
    {
        return [
            'refresh' => ['POST', '/auth/refresh.json'],
            'logoff' => ['DELETE', '/auth/logoff.json'],
            'status' => ['POST', '/auth/status.json'],
        ];
    }

    #[DataProvider('sessionRoutes')]
    public function testACrossSiteSessionRequestIsRejectedBeforeTheHandler(string $method, string $path): void
    {
        $token = $this->registered();
        $before = $this->tokenRows();

        $response = $this->dispatch($this->router, $method, $path, ['refreshToken' => $token], [
            'Sec-Fetch-Site' => 'cross-site',
            'Origin' => 'https://evil.example',
        ]);

        $this->assertSame(403, $response->status());
        $this->assertSame('FORBIDDEN', self::json($response)['error']['code']);
        $this->assertSame([], self::cookies($response));
        $this->assertSame($before, $this->tokenRows());
        $this->assertNothingSecretWasLogged();
    }

    public function testASessionFlowWithSameOriginRequests(): void
    {
        putenv('USER_APPROVAL_REQUIRED=false');
        $sameOrigin = ['Sec-Fetch-Site' => 'same-origin'];
        $first = $this->registered();

        $refreshed = $this->post($this->router, '/auth/refresh.json', ['refreshToken' => $first], $sameOrigin);
        $this->remember($refreshed);
        $token = (string) self::json($refreshed)['refreshToken'];
        $status = $this->post($this->router, '/auth/status.json', ['refreshToken' => $token], $sameOrigin);
        $logoff = $this->delete($this->router, '/auth/logoff.json', ['refreshToken' => $token], $sameOrigin);
        $after = $this->post($this->router, '/auth/refresh.json', ['refreshToken' => $token], $sameOrigin);

        $this->assertSame(201, $refreshed->status());
        $this->assertSame(['loggedIn' => true, 'isAdmin' => false], self::json($status));
        $this->assertSame(204, $logoff->status());
        $this->assertSame(401, $after->status());
        $this->assertNothingSecretWasLogged();
    }

    public function testSessionRequestsLogNoSecret(): void
    {
        $token = $this->registered();
        $other = $this->post($this->router, '/auth/login.json', ['username' => 'bob', 'password' => $this->password]);
        $this->remember($other);
        $otherToken = (string) self::json($other)['refreshToken'];
        $unknown = 'unknown-' . bin2hex(random_bytes(16));
        $this->secrets[] = $unknown;

        $refreshed = $this->post($this->router, '/auth/refresh.json', ['refreshToken' => $token]);
        $this->remember($refreshed);
        $this->rememberTokenHashes();
        $responses = [
            $refreshed,
            $this->post($this->router, '/auth/refresh.json', ['refreshToken' => $unknown]),
            $this->post($this->router, '/auth/refresh.json', ['refreshToken' => ' ' . $token]),
            $this->post($this->router, '/auth/status.json', ['refreshToken' => $unknown]),
            $this->post($this->router, '/auth/status.json', ['refreshToken' => $otherToken]),
            $this->delete($this->router, '/auth/logoff.json', ['refreshToken' => $unknown]),
            $this->delete($this->router, '/auth/logoff.json', ['refreshToken' => $otherToken]),
            $this->post($this->router, '/auth/refresh.json', ['refreshToken' => $token]),
            $this->post($this->router, '/auth/refresh.json', '{"refreshToken": ""}'),
        ];
        $statuses = array_map(static fn (Response $response): int => $response->status(), $responses);

        $this->assertSame([201, 401, 401, 201, 201, 204, 204, 401, 400], $statuses);
        $this->assertNothingSecretWasLogged();
    }

    #[DataProvider('sessionRoutes')]
    public function testAnUnexpectedSessionErrorLogsNoSecret(string $method, string $path): void
    {
        $token = $this->registered();
        $this->rememberTokenHashes();
        putenv('REFRESH_TOKEN_TTL=soon');

        $response = $this->dispatch($this->router, $method, $path, ['refreshToken' => $token]);

        $this->assertSame(500, $response->status());
        $this->assertNotSame([], $this->formatterLog->records());
        $this->assertNothingSecretWasLogged();
    }

    public function testAccountEditsLogNoSecret(): void
    {
        putenv('USER_APPROVAL_REQUIRED=false');
        putenv('ACCOUNT_EDIT_MAX_ATTEMPTS=2');
        $this->registered();
        $access = $this->accessTokenOf($this->post(
            $this->router,
            '/auth/login.json',
            ['username' => 'bob', 'password' => $this->password],
        ));
        $cookies = ['access_token' => $access];
        $newPassword = self::randomPassword('new-');
        $wrongPassword = self::randomPassword('wrong-');
        $this->secrets[] = $newPassword;
        $this->secrets[] = $wrongPassword;
        $edit = fn (array $body, array $with = []): Response => $this->patch(
            $this->router,
            '/auth/account.json',
            $body,
            $with,
        );

        $responses = [
            $edit(['currentPassword' => $this->password, 'newPassword' => implode(chr(0), ['a', 'b'])], $cookies),
            $edit(['currentPassword' => $this->password], $cookies),
            $edit(['currentPassword' => $this->password, 'newPassword' => $newPassword], $cookies),
            $edit(['currentPassword' => $wrongPassword, 'newPassword' => $this->password], $cookies),
            $edit(['currentPassword' => $wrongPassword, 'username' => 'bobby'], $cookies),
            $edit(['currentPassword' => $newPassword, 'username' => 'bobby'], $cookies),
            $edit(['currentPassword' => $newPassword, 'username' => 'bobby']),
        ];
        $this->secrets[] = (string) $this->row('users', 'username', 'bob')['password_digest'];
        $statuses = array_map(static fn (Response $response): int => $response->status(), $responses);

        $this->assertSame([400, 400, 200, 400, 400, 423, 401], $statuses);
        $this->assertNothingSecretWasLogged();
    }

    public function testACrossSiteAccountEditIsRejectedBeforeTheGate(): void
    {
        $response = $this->patch(
            $this->router,
            '/auth/account.json',
            ['currentPassword' => $this->password, 'username' => 'bobby'],
            [],
            ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example'],
        );

        $this->assertSame(403, $response->status());
        $this->assertSame('FORBIDDEN', self::json($response)['error']['code']);
        $this->assertNothingSecretWasLogged();
    }

    /**
     * Returns the access token a session response set, remembering its secrets.
     */
    private function accessTokenOf(Response $response): string
    {
        $this->assertSame(201, $response->status());
        $this->remember($response);

        foreach (self::cookies($response) as $line) {
            if (preg_match('/access_token=([^;]+);/', $line, $match) === 1) {
                return $match[1];
            }
        }

        $this->fail('No access_token cookie.');
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

    /**
     * Registers bob and returns the session's refresh token, remembering its secrets.
     */
    private function registered(): string
    {
        $response = $this->post($this->router, '/auth/register.json', $this->register());
        $this->assertSame(201, $response->status());
        $this->remember($response);

        return (string) self::json($response)['refreshToken'];
    }

    /**
     * Records every stored refresh token hash as a secret.
     */
    private function rememberTokenHashes(): void
    {
        foreach ($this->tokenRows() as $row) {
            $this->secrets[] = (string) $row['token_hash'];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tokenRows(): array
    {
        return $this->pdo->query('SELECT * FROM refresh_tokens ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertNothingSecretWasLogged(): void
    {
        $output = implode("\n", $this->formatterLog->lines()) . "\n" . (string) file_get_contents($this->errorLog);

        foreach (array_unique($this->secrets) as $secret) {
            $this->assertNotSame('', $secret);
            $this->assertStringNotContainsString($secret, $output);
        }
    }
}
