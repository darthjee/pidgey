<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PDO;
use Pidgey\Container;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\FrontController;
use Pidgey\Http\Request;
use Pidgey\Http\Response;
use Pidgey\Http\Router;

/**
 * Base class for the session-starting endpoints and their services (register, login).
 *
 * Runs without an outer test transaction, because registration opens its own: every table is
 * emptied before and after each test instead. The settings the services read are pinned
 * (`SECRET_KEY`, `ACCESS_TOKEN_TTL`, `REFRESH_TOKEN_TTL`, `USER_APPROVAL_REQUIRED`) and restored
 * afterwards; a test changes them with putenv() before building a container.
 */
abstract class SessionEndpointTestCase extends IntegrationTestCase
{
    public const SECRET_KEY = 'session-endpoint-test-secret-key-0123456789abcdef';

    public const ACCESS_TOKEN_TTL = 600;

    private const VARIABLES = ['SECRET_KEY', 'PREVIOUS_SECRET_KEYS', 'ACCESS_TOKEN_TTL', 'REFRESH_TOKEN_TTL',
        'USER_APPROVAL_REQUIRED'];

    protected PDO $pdo;

    protected Fixtures $fixtures;

    /** @var list<string> Lines the error formatter logged during the test. */
    protected array $logged = [];

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
            putenv($name);
        }

        putenv('SECRET_KEY=' . self::SECRET_KEY);
        putenv('ACCESS_TOKEN_TTL=' . self::ACCESS_TOKEN_TTL);

        $this->pdo = self::pdo();
        DatabaseCleaner::truncateAll($this->pdo);
        $this->fixtures = new Fixtures($this->pdo);
    }

    protected function tearDown(): void
    {
        DatabaseCleaner::truncateAll($this->pdo);

        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }

        parent::tearDown();
    }

    /**
     * Sends a JSON POST through a front controller with a fresh container per request; the
     * error formatter's log lines are kept in `$logged`.
     *
     * @param array<string, mixed>|string $body    Encoded as JSON unless already a string.
     * @param array<string, string>       $headers Extra request headers (e.g. `Origin`).
     */
    protected function post(Router $router, string $path, array|string $body, array $headers = []): Response
    {
        $logger = function (string $line): void {
            $this->logged[] = $line;
        };
        $controller = new FrontController($router, new ErrorFormatter(logger: $logger));

        return $controller->handle(new Request([
            'method' => 'POST',
            'uri' => $path,
            'headers' => ['Content-Type' => 'application/json', ...$headers],
            'cookies' => [],
            'body' => is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
        ]));
    }

    /**
     * Builds a container over the real database, reading the current environment.
     */
    protected function container(): Container
    {
        return new Container();
    }

    /**
     * Counts the rows of a table.
     */
    protected function rowCount(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    /**
     * Returns the only row of a table matching a column, as strings.
     *
     * @return array<string, string|null>
     */
    protected function row(string $table, string $column, string|int $value): array
    {
        $statement = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE `{$column}` = :value");
        $statement->execute(['value' => $value]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $rows);

        return $rows[0];
    }

    /**
     * Returns the decoded JSON body of a response.
     *
     * @return array<string, mixed>
     */
    protected static function json(Response $response): array
    {
        return json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the `Set-Cookie` lines of a response.
     *
     * @return list<string>
     */
    protected static function cookies(Response $response): array
    {
        return array_values(array_filter(
            $response->headers(),
            static fn (string $line): bool => str_starts_with($line, 'Set-Cookie:'),
        ));
    }
}
