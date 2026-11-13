<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Closure;
use PDO;
use Pidgey\Container;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\FrontController;
use Pidgey\Http\Request;
use Pidgey\Http\Response;
use Pidgey\Http\Router;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\User;

/**
 * Base class for the SMTP connection endpoints, through the real routes and front controller.
 *
 * Pins `SMTP_ENCRYPTION_KEY` (restored afterwards), sends every request with a fresh container
 * whose application logger is a CapturedLog, and keeps every response body. After each passing
 * test, the class canary password must appear in no response body, no captured log line and no
 * error-formatter record (`$formatterLog`).
 */
abstract class SmtpConnectionEndpointTestCase extends SessionEndpointTestCase
{
    public const ENCRYPTION_KEY = 'smtp-connection-endpoint-test-key-0123456789abcdef';

    protected Router $router;

    protected CapturedLog $log;

    /** A distinctive password, generated once per class, that must never be echoed or logged. */
    protected static string $canary;

    /** @var list<string> Every response body of the test. */
    private array $bodies = [];

    private string|false $savedEncryptionKey = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$canary = self::randomPassword('canary-smtp-password-');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedEncryptionKey = getenv('SMTP_ENCRYPTION_KEY');
        putenv('SMTP_ENCRYPTION_KEY=' . self::ENCRYPTION_KEY);
        $this->router = require __DIR__ . '/../../config/routes.php';
        $this->log = new CapturedLog();
    }

    protected function tearDown(): void
    {
        putenv(
            $this->savedEncryptionKey === false
                ? 'SMTP_ENCRYPTION_KEY'
                : 'SMTP_ENCRYPTION_KEY=' . $this->savedEncryptionKey,
        );

        parent::tearDown();
    }

    protected function assertPostConditions(): void
    {
        parent::assertPostConditions();

        foreach ($this->bodies as $body) {
            $this->assertStringNotContainsString(self::$canary, $body, 'canary password in a response body');
            $this->assertStringNotContainsString('"password"', $body, 'password field in a response body');
        }

        $this->log->assertNotLogged(self::$canary);
        $this->formatterLog->assertNotLogged(self::$canary);
    }

    /**
     * Sends a JSON request, with the user's access cookie when a user is given, through a front
     * controller whose container uses the captured log and the given connection factory.
     *
     * @param User|null                   $user    The caller, or null for an anonymous request.
     * @param array<string, mixed>|string $body    Encoded as JSON unless already a string.
     * @param array<string, string>       $headers Extra request headers.
     * @param Closure|null                $pdo     Closure(): PDO opening the request's connection;
     *                                             defaults to a new real connection.
     */
    protected function send(
        ?User $user,
        string $method,
        string $path,
        array|string $body = [],
        array $headers = [],
        ?Closure $pdo = null,
    ): Response {
        $connections = new PdoFactory();

        if ($pdo !== null) {
            $connections = $this->createStub(ConnectionFactory::class);
            $connections->method('create')->willReturnCallback($pdo);
        }

        $logger = $this->log->logger();
        $controller = new FrontController(
            $this->router,
            new ErrorFormatter(logger: $this->formatterLog->logger()),
            static fn (): Container => new Container($connections, logger: $logger),
        );
        $encoded = is_string($body) ? $body : json_encode($body === [] ? (object) [] : $body, JSON_THROW_ON_ERROR);

        $response = $controller->handle(new Request([
            'method' => $method,
            'uri' => $path,
            'headers' => ['Content-Type' => 'application/json', ...$headers],
            'cookies' => $user === null ? [] : $this->accessCookie($user),
            'body' => $encoded,
        ]));
        $this->bodies[] = $response->body();

        return $response;
    }

    /**
     * Inserts a connection with credentials, its password encrypted with the test key.
     */
    protected function connectionWithCredentials(
        int $organizationId,
        string $username = 'mailer',
        ?string $password = null,
        string $host = 'smtp.example.com',
        string $encryption = 'tls',
        bool $active = false,
    ): int {
        $id = $this->fixtures->smtpConnection(
            organizationId: $organizationId,
            host: $host,
            port: 587,
            encryption: $encryption,
            username: $username,
            active: $active,
        );
        $this->setStoredPassword($id, self::cipher()->encrypt($password ?? self::$canary, $organizationId, $id));

        return $id;
    }

    /**
     * Replaces the stored password bytes of a connection.
     */
    protected function setStoredPassword(int $id, ?string $stored): void
    {
        $statement = $this->pdo->prepare('UPDATE smtp_connections SET password = :password WHERE id = :id');
        $statement->bindValue('password', $stored, $stored === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Returns the stored password bytes of a connection, or null.
     */
    protected function storedPassword(int $id): ?string
    {
        $statement = $this->pdo->prepare('SELECT password FROM smtp_connections WHERE id = :id');
        $statement->execute(['id' => $id]);
        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }

    /**
     * The cipher over the pinned test key.
     */
    protected static function cipher(): PasswordCipher
    {
        return new PasswordCipher(new EncryptionKeys(self::ENCRYPTION_KEY));
    }

    /**
     * Returns the ids of the organization's active connections.
     *
     * @return list<int>
     */
    protected function activeIds(int $organizationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM smtp_connections WHERE organization_id = :organization_id AND active = TRUE ORDER BY id',
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Returns the `smtpConnection` item of a response.
     *
     * @return array<string, mixed>
     */
    protected static function item(Response $response): array
    {
        return self::json($response)['smtpConnection'];
    }

    /**
     * Returns the captured operator log lines as `[message, context]` pairs.
     *
     * @return list<array{string, array<string, mixed>}>
     */
    protected function operatorLines(): array
    {
        return array_map(
            static fn ($record): array => [$record->message, $record->context],
            $this->log->records(),
        );
    }

    /**
     * Asserts one operator log line with the expected action and context.
     *
     * @param list<string>|null $fields Changed field names (update only).
     */
    protected function assertOperatorLogged(
        string $action,
        User $user,
        int $connectionId,
        ?array $fields = null,
    ): void {
        $context = [
            'userId' => $user->id,
            'organizationId' => $user->organizationId,
            'connectionId' => $connectionId,
        ];

        if ($fields !== null) {
            $context['fields'] = $fields;
        }

        $this->assertSame([[$action, $context]], $this->operatorLines());
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
     * Asserts a `400 VALIDATION_FAILED` with exactly these details.
     *
     * @param list<string> $details
     */
    protected function assertValidationFailed(Response $response, array $details): void
    {
        $this->assertError($response, 400, 'VALIDATION_FAILED', null, $details);
    }
}
