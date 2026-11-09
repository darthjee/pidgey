<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * A scripted SMTP server in a child process on 127.0.0.1, for what Mailpit cannot reproduce.
 *
 * Scenario (JSON-encodable array):
 *
 * - `mode`: `plain` (default), `starttls` or `ssl` (TLS right after accept);
 * - `cert`: `server` (default, trusted, SAN includes `localhost` and `IP:127.0.0.1`),
 *   `wrong-name` or `untrusted`, from `tests/fixtures/tls`;
 * - `steps`: list of steps, each run in order:
 *   - `expect` (exact line) or `expectPrefix`: wait for that client line first;
 *   - `delayMs`: pause before replying;
 *   - `reply`: raw bytes to send (CRLF included by the scenario);
 *   - `drip`: send the reply one byte at a time, about 50 ms apart;
 *   - `then`: `close` (close and exit), `hang` (keep the connection open, never read),
 *     `starttls` (TLS handshake after the reply) or `inject` (send `bytes` right after the
 *     reply, before any handshake).
 *
 * The server serves one session; it fails (exit 1, message on stderr) when the client
 * deviates from the scenario, and exits 0 once every step ran and the client closed.
 */
final class FakeSmtpServer
{
    private const SCRIPT = __DIR__ . '/scripts/fake-smtp-server.php';
    private const START_TIMEOUT_SECONDS = 5.0;
    private const EXIT_TIMEOUT_SECONDS = 3.0;
    private const POLL_MICROSECONDS = 10000;

    /** @var array<int, self> Servers still running, stopped at shutdown. */
    private static array $running = [];

    private static bool $shutdownRegistered = false;

    private string $stdout = '';

    private string $stderr = '';

    private ?int $exitCode = null;

    /**
     * @param resource               $process
     * @param array<int, resource>   $pipes
     */
    private function __construct(
        private $process,
        private array $pipes,
        private readonly string $scenarioFile,
        private int $port = 0,
    ) {
        self::track($this);
        $this->awaitPort();
    }

    /**
     * Starts a server for the scenario and waits until it listens.
     *
     * @param array<string, mixed> $scenario
     */
    public static function start(array $scenario): self
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'pidgey-fake-smtp-');
        file_put_contents($file, json_encode($scenario, JSON_THROW_ON_ERROR));

        $process = proc_open(
            [PHP_BINARY, self::SCRIPT, $file],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if ($process === false) {
            unlink($file);
            Assert::fail('FakeSmtpServer: cannot start the server process');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return new self($process, $pipes, $file);
    }

    public function host(): string
    {
        return '127.0.0.1';
    }

    public function port(): int
    {
        return $this->port;
    }

    /**
     * Stops the server and returns its exit code.
     *
     * A server that finished on its own returns its own code; one still hanging (`then: hang`)
     * is terminated and counts as 0; one still waiting for anything else is terminated after a
     * short grace period and returns -1.
     */
    public function stop(): int
    {
        if ($this->exitCode !== null) {
            return $this->exitCode;
        }

        $code = $this->awaitExit($this->isHanging() ? 0.0 : self::EXIT_TIMEOUT_SECONDS);

        if ($code === null) {
            proc_terminate($this->process);
            $this->awaitExit(self::EXIT_TIMEOUT_SECONDS);
            $code = $this->isHanging() ? 0 : -1;
        }

        $this->drain();

        foreach ([1, 2] as $index) {
            fclose($this->pipes[$index]);
        }

        proc_close($this->process);
        @unlink($this->scenarioFile);
        unset(self::$running[spl_object_id($this)]);

        return $this->exitCode = $code;
    }

    /**
     * Stops the server, then fails unless it exited 0 with an empty stderr.
     */
    public function assertClean(): void
    {
        $code = $this->stop();

        Assert::assertSame('', $this->stderr, 'FakeSmtpServer: stderr is not empty');
        Assert::assertSame(0, $code, 'FakeSmtpServer: exit code');
    }

    /**
     * What the server wrote on stderr so far (complete after `stop()`).
     */
    public function stderr(): string
    {
        $this->drain();

        return $this->stderr;
    }

    private static function track(self $server): void
    {
        self::$running[spl_object_id($server)] = $server;

        if (self::$shutdownRegistered) {
            return;
        }

        self::$shutdownRegistered = true;
        register_shutdown_function(static function (): void {
            foreach (self::$running as $server) {
                $server->stop();
            }
        });
    }

    private function awaitPort(): void
    {
        $deadline = microtime(true) + self::START_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            $this->drain();

            if (preg_match('/^PORT=([0-9]+)\n/m', $this->stdout, $match) === 1) {
                $this->port = (int) $match[1];

                return;
            }

            if (!proc_get_status($this->process)['running']) {
                break;
            }

            usleep(self::POLL_MICROSECONDS);
        }

        $this->stop();
        Assert::fail('FakeSmtpServer: the server did not start: ' . $this->stderr);
    }

    /**
     * Waits for the process to exit on its own; returns its exit code, or null on timeout.
     */
    private function awaitExit(float $seconds): ?int
    {
        $deadline = microtime(true) + $seconds;

        do {
            $status = proc_get_status($this->process);

            if (!$status['running']) {
                return $status['signaled'] ? -1 : $status['exitcode'];
            }

            $this->drain();
            usleep(self::POLL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        return null;
    }

    private function isHanging(): bool
    {
        $this->drain();

        return str_contains($this->stdout, "HANG\n");
    }

    private function drain(): void
    {
        if ($this->exitCode !== null) {
            return;
        }

        $this->stdout .= (string) stream_get_contents($this->pipes[1]);
        $this->stderr .= (string) stream_get_contents($this->pipes[2]);
    }
}
