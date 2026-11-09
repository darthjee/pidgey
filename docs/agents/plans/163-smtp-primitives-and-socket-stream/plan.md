# Plan: SMTP primitives and socket stream

Issue: [163-smtp-primitives-and-socket-stream.md](../../issues/163-smtp-primitives-and-socket-stream.md)

## Overview

Build the SMTP foundation that #173 (transport session), #165 (send flow) and #166 (test
email) consume: `Pidgey\Infra\SecretString` and `Runtime::harden()`, the five SMTP
transport settings (validated, resolved into `Pidgey\Smtp\SessionSettings`), the reply
parser and `EHLO` extension list, the session `Deadline`, the `SmtpStream` interface with
`SocketStream` and a pure `TlsContext`, and the two test fakes (`FakeSmtpStream`,
`FakeSmtpServer`). The product owner fixes the specs the issue found contradictory or
incomplete, in the same PR. The email agent reviews the parser, `EHLO` / `STARTTLS`
handling and the close-with-no-reply rule (review only, no plan file).

## Agents involved

- [backend](backend.md)
- [product-owner](product-owner.md)

## Shared contracts

Names below are the ones the specs will record; the backend implements them, the product
owner writes them into the specs.

### `Pidgey\Infra\SecretString` (final)

- `__construct(#[SensitiveParameter] string $value)`.
- `reveal(): string` — the only accessor; throws `LogicException` after `wipe()`.
- `wipe(): void` — `sodium_memzero()` on the held value; idempotent.
- `__debugInfo(): array` → `['value' => '[redacted]']`.
- `__serialize()`, `__unserialize()` and `__clone()` throw `LogicException`.
- Implements **neither** `__toString` nor `JsonSerializable` (`(string)` cast throws
  `Error`; Monolog logs the class name only).

### `Pidgey\Infra\Runtime` (final)

- `Runtime::harden(): void` — outside `PHP_ENV` `development` / `test` (unset = production)
  runs `ini_set('zend.exception_ignore_args', '1')`. Called right after `.env` is loaded by
  `public/index.php`, `bin/create-db`, `bin/drop-db`, `bin/migrate`,
  `bin/reencrypt-smtp-passwords`, `bin/wait-for-db`, and inside
  `CheckConfigCommand::run()` after its own `EnvFile::load()`.

### `Pidgey\Infra\AppPath` (final)

- `AppPath::root(): string` — absolute path of `source/` (the app root).
- `AppPath::resolve(string $path): string` — absolute paths unchanged; relative paths
  joined to `root()`. Used by `ConfigValidator` and `Container` for `SMTP_CA_FILE`.

### Configuration

- `ConfigValidator` constants: `DEFAULT_SMTP_CONNECT_TIMEOUT = 10`,
  `DEFAULT_SMTP_COMMAND_TIMEOUT = 10`, `DEFAULT_SMTP_TOTAL_TIMEOUT = 25`.
- Failure lines (never a value): `SMTP_CONNECT_TIMEOUT: not a positive integer` (same for
  `SMTP_COMMAND_TIMEOUT`, `SMTP_TOTAL_TIMEOUT`),
  `SMTP_CONNECT_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT`,
  `SMTP_COMMAND_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT`, `SMTP_CA_FILE: not readable`,
  `SMTP_EHLO_NAME: invalid hostname`.
- Precedence: an invalid timeout is skipped by the `exceeds` check; an invalid
  `SMTP_TOTAL_TIMEOUT` skips both `exceeds` checks; comparisons use effective values
  (defaults applied).
- `Pidgey\Smtp\SessionSettings` (final, readonly):
  `__construct(float $connectTimeout, float $commandTimeout, float $totalTimeout, ?string $caFile, ?string $ehloName)`;
  `caFile` is absolute (or null), `ehloName` null when unset / empty.
- `Container::smtpSessionSettings(): SessionSettings` — memoized, built from `Env` with the
  defaults above and `AppPath::resolve()`; no new constructor argument.

### `Pidgey\Smtp\` primitives

- `Deadline` (final): `Deadline::start(Clock $clock, float $seconds): self`,
  `remaining(): float` (never below `0.0`), `expired(): bool`,
  `within(float $seconds): self` (a child expiring at
  `min(parent expiry, now + seconds)`).
- `Reply` (final, readonly): `code(): int`, `lines(): list<string>` (texts without code and
  separator), `text(): string` (joined with one space; a shared enhanced status code
  `X.Y.Z` whose `X` equals the code's first digit kept once at the front).
- `ReplyParser` (final): `read(SmtpStream $stream, Deadline $budget): Reply`; limits
  `MAX_LINES = 100`, `MAX_LINE_BYTES = 2048` (content without CRLF); throws
  `MalformedReply` (malformed line, code outside 200–599, mixed codes, limit exceeded,
  close mid-reply). A `StreamFailure` before the first byte propagates unchanged.
- `EhloExtensions` (final): `fromReply(Reply $reply): self` (first line = greeting name,
  skipped), `has(string $keyword): bool` (case-insensitive),
  `authMechanisms(): list<string>` (upper case, union of `AUTH x y` and `AUTH=x y` lines).
- `MalformedReply extends RuntimeException` — fixed message, no reply bytes.
- `StreamFailureKind` (enum): `Connect`, `Tls`, `Timeout`, `Closed`, `UnexpectedData`.
- `StreamFailure extends RuntimeException`: `kind(): StreamFailureKind`,
  `reason(): ?string` (OpenSSL reason, `Tls` only, for the log line); the message is fixed
  text per kind, never sent or received bytes.
- `SmtpStream` (interface):
  - `open(string $address, int $port, string $peerName, bool $implicitTls, ?string $caFile, Deadline $deadline): void`;
  - `localAddress(): string` (raw IP of the local socket);
  - `writeLine(#[SensitiveParameter] string $line, Deadline $deadline): void` (CRLF added;
    loops on partial writes);
  - `readLine(Deadline $deadline): string` — raw bytes up to and including LF, at most
    `MAX_LINE_BYTES + 2` bytes; a result without a trailing LF means too long or closed
    mid-line; `Closed` when the peer closes before any byte;
  - `upgradeToTls(string $peerName, ?string $caFile, Deadline $deadline): void` —
    `UnexpectedData` when bytes are pending (PHP buffer or socket) before the handshake;
  - `close(): void` (idempotent).
- `TlsContext` (final): `TlsContext::options(string $peerName, ?string $caFile): array` —
  the `['ssl' => …]` context: `verify_peer` and `verify_peer_name` true, `peer_name`,
  `SNI_enabled` false and no `SNI_server_name` for IP literals (else `SNI_server_name` =
  peer name), `crypto_method` TLS 1.2 | 1.3 client, `cafile` when set,
  `capture_peer_cert` false.
- `SocketStream` (final) implements `SmtpStream`: always `tcp://` (IPv6 as `[addr]`), then
  `stream_socket_enable_crypto` non-blocking with `stream_select` for `ssl` and upgrades.

### Test support (`Pidgey\Tests\Support\`)

- `FakeSmtpStream(FrozenClock $clock)` implements `SmtpStream`, scripted in order:
  `expectOpen()`, `expectLine(string $line)`, `expectLinePrefix(string $prefix)`,
  `reply(string $raw, float $delay = 0.0)`, `expectUpgrade()`,
  `fail(StreamFailureKind $kind, float $delay = 0.0)`, `assertDone()`. A delay advances
  the clock; a delay beyond the read's remaining budget advances the clock by that budget
  and throws `Timeout`. A mismatched client line fails the test.
- `FakeSmtpServer::start(array $scenario): self`, `host(): string` (`127.0.0.1`),
  `port(): int`, `stop(): int` (exit code), `assertClean(): void` (exit 0, empty stderr).
  Scenario: `mode` (`plain` / `starttls` / `ssl`), `cert` (`server` / `wrong-name` /
  `untrusted`), `steps[]` with `expect` (exact) or `expectPrefix`, `reply` (raw bytes),
  `delayMs`, `drip` (bool, one byte at a time), `then` (`close` / `hang` / `starttls` /
  `inject`, with `inject` carrying `bytes`). Script:
  `source/tests/support/scripts/fake-smtp-server.php`.

### TLS fixtures

- `server.pem` SAN becomes `DNS:mailpit-starttls,DNS:mailpit-ssl,DNS:localhost,IP:127.0.0.1`
  (regenerated with `generate.sh`; every fixture file changes, since a new CA is created).
