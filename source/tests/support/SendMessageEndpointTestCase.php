<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PDO;
use Pidgey\Auth\ApiTokenValue;
use Pidgey\Container;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\FrontController;
use Pidgey\Http\Request;
use Pidgey\Http\Response;
use Pidgey\Http\Router;
use Pidgey\Infra\PdoFactory;
use Pidgey\Smtp\HostPolicy;

/**
 * Base class for `POST /v1/messages`, through the real routes and front controller.
 *
 * Runs without an outer test transaction (every table is emptied before and after each test),
 * pins the settings the send reads and restores them afterwards. Every request goes through a
 * fresh container whose application logger is a CapturedLog (`$log`); `$formatterLog` captures
 * what the front controller's error formatter logs.
 */
abstract class SendMessageEndpointTestCase extends IntegrationTestCase
{
    public const ENCRYPTION_KEY = 'send-message-endpoint-test-key-0123456789abcdef';

    protected const PATH = '/v1/messages';

    protected const PAYLOAD = ['to' => 'user@example.com', 'subject' => 'Hello', 'text' => 'Hi!'];

    private const VARIABLES = [
        'SMTP_ALLOW_PRIVATE_HOSTS',
        'SMTP_ENCRYPTION_KEY',
        'SMTP_CONNECT_TIMEOUT',
        'SMTP_COMMAND_TIMEOUT',
        'SMTP_TOTAL_TIMEOUT',
        'ALLOWED_ORIGINS',
        'FRONTEND_BASE_URL',
    ];

    protected PDO $pdo;

    protected Fixtures $fixtures;

    protected Router $router;

    protected CapturedLog $log;

    protected CapturedLog $formatterLog;

    protected Mailpit $mailpit;

    /** @var array<string, string|false> */
    private array $saved = [];

    /** @var list<FakeSmtpServer> */
    private array $servers = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
        }

        putenv('SMTP_ALLOW_PRIVATE_HOSTS=true');
        putenv('SMTP_ENCRYPTION_KEY=' . self::ENCRYPTION_KEY);
        putenv('SMTP_CONNECT_TIMEOUT=2');
        putenv('SMTP_COMMAND_TIMEOUT=1');
        putenv('SMTP_TOTAL_TIMEOUT=5');
        putenv('ALLOWED_ORIGINS');
        putenv('FRONTEND_BASE_URL');

        $this->pdo = self::pdo();
        DatabaseCleaner::truncateAll($this->pdo);
        $this->fixtures = new Fixtures($this->pdo);
        $this->router = require __DIR__ . '/../../config/routes.php';
        $this->log = new CapturedLog();
        $this->formatterLog = new CapturedLog();
        $this->mailpit = Mailpit::plain();
        $this->mailpit->clear();
    }

    protected function tearDown(): void
    {
        foreach ($this->servers as $server) {
            $server->stop();
        }

        DatabaseCleaner::truncateAll($this->pdo);

        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }

        parent::tearDown();
    }

    /**
     * Creates an organization with an approved user, an API token and, optionally, an active
     * connection to the plain Mailpit.
     *
     * @param list<string> $scopes
     *
     * @return array{organizationId: int, token: string, tokenId: int}
     */
    protected function account(array $scopes = ['send'], bool $connected = true): array
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->user(organizationId: $organizationId);
        $token = ApiTokenValue::generate();
        $tokenId = $this->fixtures->apiToken(
            organizationId: $organizationId,
            tokenHash: ApiTokenValue::hash($token),
            scopes: $scopes,
        );

        if ($connected) {
            $this->connect($organizationId, $this->mailpit->host(), $this->mailpit->smtpPort());
        }

        return ['organizationId' => $organizationId, 'token' => $token, 'tokenId' => $tokenId];
    }

    /**
     * Inserts an active connection to a host and port, without credentials or encryption.
     */
    protected function connect(int $organizationId, string $host, int $port): int
    {
        return $this->fixtures->smtpConnection(
            organizationId: $organizationId,
            host: $host,
            port: $port,
            active: true,
            fromAddress: 'sender@pidgey.test',
        );
    }

    /**
     * Starts a scripted plain SMTP server and makes it the organization's only active connection.
     *
     * @param list<array<string, mixed>> $steps Steps of the scenario (see FakeSmtpServer).
     */
    protected function scriptedConnection(int $organizationId, array $steps): void
    {
        $server = FakeSmtpServer::start(['mode' => 'plain', 'steps' => $steps]);
        $this->servers[] = $server;
        $this->pdo->prepare('DELETE FROM smtp_connections WHERE organization_id = :id')
            ->execute(['id' => $organizationId]);
        $this->connect($organizationId, $server->host(), $server->port());
    }

    /**
     * Points the organization's only active connection to the plain Mailpit.
     */
    protected function mailpitConnection(int $organizationId): void
    {
        $this->pdo->prepare('DELETE FROM smtp_connections WHERE organization_id = :id')
            ->execute(['id' => $organizationId]);
        $this->connect($organizationId, $this->mailpit->host(), $this->mailpit->smtpPort());
    }

    /**
     * The steps of a server that greets, answers `EHLO` and `MAIL FROM`, then refuses `RCPT TO`
     * with the given reply.
     *
     * @return list<array<string, mixed>>
     */
    protected static function untilRecipient(string $reply): array
    {
        return [
            ['reply' => "220 fake ready\r\n"],
            ['expectPrefix' => 'EHLO ', 'reply' => "250 fake\r\n"],
            ['expectPrefix' => 'MAIL FROM:<', 'reply' => "250 ok\r\n"],
            ['expectPrefix' => 'RCPT TO:<', 'reply' => $reply],
        ];
    }

    /**
     * Sends `POST /v1/messages` with a Bearer token.
     *
     * @param array<string, mixed>|string $body    Encoded as JSON unless already a string.
     * @param array<string, string>       $headers Extra request headers.
     */
    protected function post(?string $token, array|string $body = self::PAYLOAD, array $headers = []): Response
    {
        return $this->request(
            'POST',
            $token === null ? $headers : ['Authorization' => 'Bearer ' . $token, ...$headers],
            $body,
        );
    }

    /**
     * Sends a request through the real routes with a fresh container.
     *
     * @param array<string, string>       $headers
     * @param array<string, mixed>|string $body
     * @param array<string, string>       $cookies
     */
    protected function request(
        string $method,
        array $headers,
        array|string $body = self::PAYLOAD,
        ?HostPolicy $hostPolicy = null,
        array $cookies = [],
        string $path = self::PATH,
    ): Response {
        $logger = $this->log->logger();
        $controller = new FrontController(
            $this->router,
            new ErrorFormatter(logger: $this->formatterLog->logger()),
            static fn (): Container => new Container(new PdoFactory(), logger: $logger, hostPolicy: $hostPolicy),
        );

        return $controller->handle(new Request([
            'method' => $method,
            'uri' => $path,
            'headers' => ['Content-Type' => 'application/json', ...$headers],
            'cookies' => $cookies,
            'body' => is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function json(Response $response): array
    {
        return json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR);
    }

    /**
     * The body of an error answer without its timestamp, to compare answers sent at different times.
     */
    protected static function withoutTimestamp(Response $response): string
    {
        $body = self::json($response);
        unset($body['timestamp']);

        return json_encode($body, JSON_THROW_ON_ERROR);
    }

    /**
     * Asserts an error envelope's status, code and message (and details when given).
     *
     * @param list<string>|null $details
     */
    protected function assertError(
        Response $response,
        int $status,
        string $code,
        ?string $message = null,
        ?array $details = null,
    ): void {
        $this->assertSame($status, $response->status(), $response->body());
        $error = self::json($response)['error'];
        $this->assertSame($code, $error['code']);

        if ($message !== null) {
            $this->assertSame($message, $error['message']);
        }

        if ($details !== null) {
            $this->assertSame($details, $error['details'] ?? null);
        }
    }

    /**
     * The `Idempotent-Replayed` header value of a response, or null when it was not sent.
     */
    protected static function replayed(Response $response): ?string
    {
        foreach ($response->headers() as $line) {
            if (str_starts_with($line, 'Idempotent-Replayed: ')) {
                return substr($line, strlen('Idempotent-Replayed: '));
            }
        }

        return null;
    }

    /**
     * The `smtp-send-attempt` log lines of the test.
     *
     * @return list<array<string, mixed>> The context of each line.
     */
    protected function attempts(): array
    {
        return array_values(array_map(
            static fn ($record): array => $record->context,
            array_filter($this->log->records(), static fn ($record): bool => $record->message === 'smtp-send-attempt'),
        ));
    }

    /**
     * @return list<array<string, mixed>> The `idempotency_keys` rows, oldest first.
     */
    protected function keyRows(): array
    {
        return $this->pdo->query('SELECT * FROM idempotency_keys ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function sentCount(): int
    {
        return count($this->mailpit->messages());
    }
}
