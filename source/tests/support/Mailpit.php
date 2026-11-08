<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use JsonException;
use PHPUnit\Framework\Assert;
use Pidgey\Infra\Env;

/**
 * Client for one test Mailpit instance: its SMTP address and its HTTP API (clear, list, read).
 *
 * Built from a `TEST_MAILPIT_*` key of the form `host:smtpPort:apiPort`; it is the only reader
 * of those keys. A missing or malformed key fails the test, it is never skipped.
 */
final class Mailpit
{
    /** Must match `MP_SMTP_AUTH` of the STARTTLS and SSL instances (compose and CI). */
    public const AUTH_USER = 'pidgey-test';
    public const AUTH_PASSWORD = 'pidgey-test-password';

    private const TIMEOUT_SECONDS = 5;

    private function __construct(
        private readonly string $host,
        private readonly int $smtpPort,
        private readonly int $apiPort,
    ) {
    }

    /**
     * The plain instance (no TLS, no auth).
     */
    public static function plain(): self
    {
        return self::fromKey('TEST_MAILPIT_PLAIN');
    }

    /**
     * The instance requiring STARTTLS and auth.
     */
    public static function starttls(): self
    {
        return self::fromKey('TEST_MAILPIT_STARTTLS');
    }

    /**
     * The implicit-TLS instance requiring auth.
     */
    public static function ssl(): self
    {
        return self::fromKey('TEST_MAILPIT_SSL');
    }

    public function host(): string
    {
        return $this->host;
    }

    public function smtpPort(): int
    {
        return $this->smtpPort;
    }

    public function apiPort(): int
    {
        return $this->apiPort;
    }

    /**
     * Deletes every message of the instance.
     */
    public function clear(): void
    {
        $this->request('DELETE', '/api/v1/messages');
    }

    /**
     * The message summaries of the instance, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function messages(): array
    {
        $body = $this->json('/api/v1/messages');
        $messages = $body['messages'] ?? null;

        if (!is_array($messages)) {
            Assert::fail('Mailpit GET /api/v1/messages returned no "messages" list.');
        }

        return array_values($messages);
    }

    /**
     * One full message, by its Mailpit id.
     *
     * @return array<string, mixed>
     */
    public function message(string $id): array
    {
        return $this->json('/api/v1/message/' . rawurlencode($id));
    }

    private static function fromKey(string $key): self
    {
        $value = Env::get($key);

        if ($value === null) {
            Assert::fail("Missing test environment variable {$key} (expected host:smtpPort:apiPort).");
        }

        if (preg_match('/^([^:\s]+):([0-9]{1,5}):([0-9]{1,5})$/', $value, $parts) !== 1) {
            Assert::fail("Malformed test environment variable {$key}: {$value} (expected host:smtpPort:apiPort).");
        }

        return new self($parts[1], (int) $parts[2], (int) $parts[3]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $path): array
    {
        $url = $this->url($path);
        $body = $this->request('GET', $path);

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Assert::fail("Mailpit GET {$url} returned invalid JSON: {$exception->getMessage()}");
        }

        if (!is_array($decoded)) {
            Assert::fail("Mailpit GET {$url} returned JSON that is not an object.");
        }

        return $decoded;
    }

    private function request(string $method, string $path): string
    {
        $url = $this->url($path);
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = $this->status(http_get_last_response_headers() ?? []);

        if ($body === false || $status < 200 || $status > 299) {
            $shown = $status === 0 ? 'no response' : (string) $status;
            Assert::fail("Mailpit {$method} {$url} failed with status {$shown}.");
        }

        return $body;
    }

    /**
     * @param list<string> $headers
     */
    private function status(array $headers): int
    {
        $statusLine = $headers[0] ?? '';

        if (preg_match('#^HTTP/\S+\s+([0-9]{3})#', $statusLine, $parts) !== 1) {
            return 0;
        }

        return (int) $parts[1];
    }

    private function url(string $path): string
    {
        return "http://{$this->host}:{$this->apiPort}{$path}";
    }
}
