<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use PHPUnit\Framework\Assert;
use Pidgey\Smtp\Deadline;
use Pidgey\Smtp\ReplyParser;
use Pidgey\Smtp\SmtpStream;
use Pidgey\Smtp\StreamFailure;
use Pidgey\Smtp\StreamFailureKind;
use SensitiveParameter;

/**
 * In-memory `SmtpStream`, scripted in order: each call consumes the next script entry and
 * fails the test when it does not match.
 *
 * - `expectOpen()` / `expectUpgrade()`: consumed by `open()` / `upgradeToTls()`; a `fail()`
 *   right after makes that call throw.
 * - `expectLine()` / `expectLinePrefix()`: consumed by `writeLine()`.
 * - `reply()`: its raw bytes are returned line by line by `readLine()`, at most
 *   `ReplyParser::MAX_LINE_BYTES + 2` bytes per read, as the socket stream does.
 * - `fail()`: thrown by the call that reaches it (or by the `open()` / `upgradeToTls()` it
 *   follows).
 *
 * Delays advance the shared `FrozenClock`; a delay beyond the call's remaining deadline
 * advances it by that remaining time and throws a `Timeout`. A call with an expired deadline
 * throws a `Timeout` at once and consumes nothing.
 */
final class FakeSmtpStream implements SmtpStream
{
    private const OPEN = 'open';
    private const LINE = 'line';
    private const LINE_PREFIX = 'line prefix';
    private const REPLY = 'reply';
    private const UPGRADE = 'upgrade';
    private const FAIL = 'fail';

    /** @var list<array{type: string, value: string, delay: float, kind: StreamFailureKind|null}> */
    private array $script = [];

    /** Unread bytes of the reply being read. */
    private ?string $pending = null;

    private bool $closed = false;

    /** @var list<array{address: string, port: int, peerName: string, implicitTls: bool, caFile: string|null}> */
    private array $opens = [];

    /** @var list<array{peerName: string, caFile: string|null}> */
    private array $upgrades = [];

    public function __construct(
        private readonly FrozenClock $clock,
        private readonly string $localAddress = '192.0.2.10',
    ) {
    }

    public function expectOpen(): self
    {
        return $this->add(self::OPEN);
    }

    public function expectLine(string $line): self
    {
        return $this->add(self::LINE, $line);
    }

    public function expectLinePrefix(string $prefix): self
    {
        return $this->add(self::LINE_PREFIX, $prefix);
    }

    public function reply(string $raw, float $delay = 0.0): self
    {
        return $this->add(self::REPLY, $raw, $delay);
    }

    public function expectUpgrade(): self
    {
        return $this->add(self::UPGRADE);
    }

    public function fail(StreamFailureKind $kind, float $delay = 0.0): self
    {
        return $this->add(self::FAIL, '', $delay, $kind);
    }

    /**
     * Fails when script entries (or reply bytes) remain unconsumed.
     */
    public function assertDone(): void
    {
        $left = array_map(static fn (array $entry): string => $entry['type'], $this->script);

        if ($this->pending !== null) {
            array_unshift($left, 'rest of a reply');
        }

        Assert::assertSame([], $left, 'FakeSmtpStream: script entries left');
    }

    /**
     * The arguments of every `open()` call, in order.
     *
     * @return list<array{address: string, port: int, peerName: string, implicitTls: bool, caFile: string|null}>
     */
    public function opens(): array
    {
        return $this->opens;
    }

    /**
     * The arguments of every `upgradeToTls()` call, in order.
     *
     * @return list<array{peerName: string, caFile: string|null}>
     */
    public function upgrades(): array
    {
        return $this->upgrades;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function open(
        string $address,
        int $port,
        string $peerName,
        bool $implicitTls,
        ?string $caFile,
        Deadline $deadline,
    ): void {
        $this->checkUsable($deadline);
        $this->opens[] = compact('address', 'port', 'peerName', 'implicitTls', 'caFile');
        $this->consumeOperation(self::OPEN, $deadline);
    }

    public function localAddress(): string
    {
        return $this->localAddress;
    }

    public function writeLine(#[SensitiveParameter] string $line, Deadline $deadline): void
    {
        $this->checkUsable($deadline);
        $entry = $this->next('writeLine');

        if ($entry['type'] === self::FAIL) {
            $this->throwFailure($entry, $deadline);
        }

        $matches = match ($entry['type']) {
            self::LINE => $line === $entry['value'],
            self::LINE_PREFIX => str_starts_with($line, $entry['value']),
            default => Assert::fail("FakeSmtpStream: writeLine() called, expected {$entry['type']}"),
        };

        if (!$matches) {
            Assert::fail(sprintf(
                'FakeSmtpStream: expected %s "%s", got "%s"',
                $entry['type'],
                $entry['value'],
                $line,
            ));
        }
    }

    public function readLine(Deadline $deadline): string
    {
        $this->checkUsable($deadline);

        if ($this->pending === null) {
            $entry = $this->next('readLine');

            if ($entry['type'] === self::FAIL) {
                $this->throwFailure($entry, $deadline);
            }

            if ($entry['type'] !== self::REPLY) {
                Assert::fail("FakeSmtpStream: readLine() called, expected {$entry['type']}");
            }

            $this->wait($entry['delay'], $deadline);
            $this->pending = $entry['value'];
        }

        return $this->takeLine();
    }

    public function upgradeToTls(string $peerName, ?string $caFile, Deadline $deadline): void
    {
        $this->checkUsable($deadline);
        $this->upgrades[] = compact('peerName', 'caFile');
        $this->consumeOperation(self::UPGRADE, $deadline);
    }

    public function close(): void
    {
        $this->closed = true;
    }

    private function add(string $type, string $value = '', float $delay = 0.0, ?StreamFailureKind $kind = null): self
    {
        $this->script[] = ['type' => $type, 'value' => $value, 'delay' => $delay, 'kind' => $kind];

        return $this;
    }

    /**
     * Fails the test on a closed stream; throws a `Timeout` on an expired deadline.
     */
    private function checkUsable(Deadline $deadline): void
    {
        if ($this->closed) {
            Assert::fail('FakeSmtpStream: used after close()');
        }

        if ($deadline->expired()) {
            throw new StreamFailure(StreamFailureKind::Timeout);
        }
    }

    /**
     * Consumes an `open` or `upgrade` expectation, then a `fail()` that follows it.
     */
    private function consumeOperation(string $type, Deadline $deadline): void
    {
        $entry = $this->next($type);

        if ($entry['type'] !== $type) {
            Assert::fail("FakeSmtpStream: {$type} called, expected {$entry['type']}");
        }

        if (($this->script[0]['type'] ?? null) === self::FAIL) {
            $this->throwFailure($this->next($type), $deadline);
        }
    }

    /**
     * @return array{type: string, value: string, delay: float, kind: StreamFailureKind|null}
     */
    private function next(string $call): array
    {
        $entry = array_shift($this->script);

        if ($entry === null) {
            Assert::fail("FakeSmtpStream: {$call} called, script is done");
        }

        return $entry;
    }

    /**
     * @param array{type: string, value: string, delay: float, kind: StreamFailureKind|null} $entry
     */
    private function throwFailure(array $entry, Deadline $deadline): never
    {
        $this->wait($entry['delay'], $deadline);

        throw new StreamFailure($entry['kind'] ?? StreamFailureKind::Closed, 'fake TLS failure');
    }

    /**
     * Advances the clock by the delay, or by the remaining time and throws a `Timeout`.
     */
    private function wait(float $delay, Deadline $deadline): void
    {
        $remaining = $deadline->remaining();

        if ($delay > $remaining) {
            $this->clock->sleep($remaining);

            throw new StreamFailure(StreamFailureKind::Timeout);
        }

        $this->clock->sleep($delay);
    }

    /**
     * Returns the next line of the pending reply, as the socket stream would read it.
     */
    private function takeLine(): string
    {
        $pending = (string) $this->pending;
        $limit = ReplyParser::MAX_LINE_BYTES + 2;
        $end = strpos($pending, "\n");
        $length = $end === false ? strlen($pending) : $end + 1;
        $line = substr($pending, 0, min($length, $limit));
        $rest = substr($pending, strlen($line));
        $this->pending = $rest === '' ? null : $rest;

        return $line;
    }
}
