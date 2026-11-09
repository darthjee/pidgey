# Issue: SMTP primitives and socket stream

## Description
Part of epic #149 (email integration). Implements the SMTP **protocol primitives and the
socket stream** (replies, deadline, stream abstraction, connect, implicit TLS, `STARTTLS`
upgrade, verification policy) as settled by #152. The session itself (`SmtpTransport`) is
the follow-up issue (see Scope boundaries).

### Specs

- `docs/agents/specs/email/send-flow.md` (replies, timeouts, secret hygiene);
- `docs/agents/specs/email/smtp-connections.md#tls-policy`;
- `docs/agents/specs/architecture/configuration.md#smtp-transport`;
- `docs/agents/specs/email/testing.md` (fake stream, fake server, TLS fixtures);
- implementation notes: `docs/agents/specs/implement-email/transport.md` (items 1, 2, 3, 6,
  10, and the Stream shared contract) and `implement-email/testing.md`.

### Dependencies

#161 (email test infrastructure: TLS fixtures, Mailpit); #162 (logger and clock
foundation). Blocks #173 (SMTP transport session).

Agents: **backend** (code, tests, fixture regeneration), **product-owner** (the spec fixes
listed under Edge cases and Layering), **email** (review of the reply parser, `EHLO` /
`STARTTLS` handling and the close-with-no-reply classification).

## Problem

The SMTP transport (#173), the send flow (#165) and the test email (#166) need a tested
foundation to talk SMTP: a reply parser with RFC 5321 limits, a session deadline, a stream
abstraction with a socket implementation that enforces the TLS policy (always verify,
TLS 1.2+, peer name and SNI rules, `SMTP_CA_FILE`), a secret value object, validated
transport settings, and test fakes that reproduce what Mailpit cannot. Several specs
disagree or leave gaps here (`SecretString` rules and namespace, a close with no reply,
untestable IP-SAN rule), which must be settled before code builds on them.

## Expected Behavior

### Scope

- `Pidgey\Smtp\ReplyParser` / `Reply`: multi-line, limits (100 lines, 2048 bytes per
  line), malformed replies, `EHLO` extensions, the joined reply text.
- `Pidgey\Smtp\Deadline` on `Pidgey\Infra\Clock`: every wait is
  `min(its own timeout, time remaining)`.
- `Pidgey\Smtp\SmtpStream` (interface, per the Stream shared contract) and its stream-level
  failures (connect failure, TLS failure with the OpenSSL reason, timeout, closed by peer).
- `Pidgey\Smtp\SocketStream`: connect to the checked IP, implicit TLS, in-place `STARTTLS`
  upgrade with TLS 1.2+ and the verification policy (`SMTP_CA_FILE`, peer name = `host`,
  IP SAN and no SNI for IP literals), local address, per-operation timeouts, the
  pending-bytes report used by the injection guard.
- `Pidgey\Infra\SecretString` (see Layering), `#[SensitiveParameter]`, `sodium_memzero()`.
- `ConfigValidator` / `bin/check-config`: the five SMTP transport keys —
  `SMTP_CONNECT_TIMEOUT`, `SMTP_COMMAND_TIMEOUT`, `SMTP_TOTAL_TIMEOUT`, `SMTP_CA_FILE`,
  `SMTP_EHLO_NAME` — with the rules and failure names of
  `architecture/configuration.md#smtp-transport`; `zend.exception_ignore_args` at web and
  CLI boot.
- Test support:
  - `FakeSmtpStream`: in memory, scripted (expected client lines, server replies, and
    failures: timeout, closed, TLS failure, pending bytes after `STARTTLS`);
  - `FakeSmtpServer`: child process on `127.0.0.1`, ephemeral port, JSON scenario, plain /
    `STARTTLS` / implicit TLS with the fixture certificates. Its scenario format must already
    cover what the transport issue needs (no `STARTTLS` advertised, no supported `AUTH`,
    multi-line, malformed and `4xx` replies, accept-and-never-answer).
- Tests: parser and deadline unit tests; `FakeSmtpStream` self-tests; `SocketStream`
  integration tests against the fake server (timeouts, wrong-name and untrusted
  certificates, pending bytes) and the Mailpit `plain` / `starttls` / `ssl` instances
  (connect and upgrade succeed with the test CA).

### Edge cases

#### Spec fixes (made in this issue's PR)

1. **`SecretString` contradiction:** `email/send-flow.md#secret-hygiene` says `__toString`
   and `jsonSerialize` return `[redacted]`, but `architecture/backend.md#logging` (#162)
   says it implements **neither**. Decision: follow #162.
   - No `__toString`, no `JsonSerializable`: an accidental `(string)` cast throws instead of
     silently sending `[redacted]` as the password.
   - `__debugInfo` returns `[redacted]`; `__serialize` and `__clone` throw.
   - `reveal()` is the only accessor; `wipe()` runs `sodium_memzero()`, after which
     `reveal()` throws.
   - Fix `send-flow.md` accordingly.
2. **Peer closes with no reply at all** (not mid-reply, not between replies): the stream
   reports a distinct "closed" failure. Classification (implemented by the transport
   follow-up, written into `send-flow.md#replies` / `failures.md#classification-rules`
   here): at the greeting → `SMTP_CONNECTION_FAILED` (servers that block a client often
   accept TCP then drop it); at any later step → `SMTP_PROTOCOL_ERROR`; during `QUIT` →
   ignored.
3. **IP SAN fixture:** the "IP-literal `host` must match an IP SAN, no SNI" rule is untestable
   with the current fixtures. Add `IP:127.0.0.1` to `server.pem` in `generate.sh`,
   regenerate, and update the fixture table of `email/testing.md`.

#### Reply parser

4. The 2048-byte line limit counts the content **without** CRLF; no LF within 2048 + 2
   bytes → malformed. An empty line (bare CRLF) is malformed. `NNN` without text and `NNN-`
   with empty text are valid.
5. `EHLO`: the first line is always the greeting name, never an extension; keywords are
   case-insensitive; both `AUTH PLAIN LOGIN` and the legacy `AUTH=LOGIN PLAIN` are accepted,
   and several `AUTH` lines merge into the union of their mechanisms.
6. The enhanced status code is recognized only as `X.Y.Z` with `X` equal to the reply
   code's first digit; otherwise the text is kept as is. Raw bytes (NUL, stray CR,
   non-ASCII) pass through untouched; sanitization belongs to the send flow.

#### Stream and deadline

7. **Timeouts are per reply, not per line** (slowloris: one line every 9 s must not reset the
   clock): one budget `min(command timeout, deadline remaining)` is computed per reply, and
   each line read gets what remains of it.
8. **Writes** loop until every byte is written (partial writes under TLS), bounded by the
   same `min(command timeout, deadline remaining)`. Remaining time ≤ 0 → immediate timeout
   failure, no I/O.
9. **Connect is always `tcp://`**, then `stream_socket_enable_crypto` (for `ssl` and after
   `STARTTLS`), so a connect failure stays distinct from a TLS failure. The handshake runs
   non-blocking with `stream_select`, since a blocking handshake is not bounded by
   `stream_set_timeout`.
10. **Pending bytes after `STARTTLS` `220`:** check both PHP's read buffer
    (`unread_bytes`) and a non-blocking read on the socket before the handshake.
11. **IPv6:** target `tcp://[addr]:port`; the local address literal is `[IPv6:…]`.
12. **TLS 1.0 / 1.1 refusal is not tested** (the image's OpenSSL 3 cannot offer them at the
    default security level); `crypto_method` = TLS 1.2 | 1.3 client, asserted in code.

#### Config

13. A relative `SMTP_CA_FILE` is resolved against the app root (`source/`), not the working
    directory (web and CLI differ); the spec's `tests/fixtures/tls/ca.pem` is relative.
14. The keys are integer seconds; the stream and `Deadline` APIs take floats, so tests pass
    sub-second timeouts (e.g. 0.2 s) directly, without `Env`.

### Layering

- `SecretString` lives in **`Pidgey\Infra\SecretString`**, not `Pidgey\Email\`: the transport
  and stream (`Pidgey\Smtp\`) take it as input, and `Smtp` must never depend on `Email`.
  `Smtp`'s rule becomes "depends only on `Pidgey\Infra\`" (`Clock`, `SecretString`).
- Rejected: `Pidgey\Smtp\SecretString` (`Crypto` could never return it; a generic type in a
  protocol namespace) and `Pidgey\Crypto\SecretString` (a second `Smtp` dependency;
  `Crypto` is not in the layer table).
- `PasswordCipher::decrypt()` keeps returning `string` (no change to #160's code); the send
  flow (#165) wraps the plain password into a `SecretString` immediately.
- Spec fixes in this issue's PR: `architecture/backend.md` (Features, SMTP transport and
  Infrastructure rows, the dependency sentence), `email/send-flow.md#secret-hygiene`,
  `implement-email/transport.md` (proposed names, item 6).

### Configuration

1. **Kept in this issue:** `Deadline` and `SocketStream` consume the timeouts and
   `SMTP_CA_FILE`, and the relative-path rule (edge case 13) must be shared by the validator
   and the stream.
2. **Settings object:** `Pidgey\Smtp\SessionSettings` (proposal) carries the connect, command
   and total timeouts (floats), `caFile` (absolute path or null) and `ehloName` (or null).
   `Pidgey\Smtp\` never reads `Env`: `Container::smtpSessionSettings()` builds it once
   (memoized) from `Env`, with defaults from `ConfigValidator` constants
   (`DEFAULT_SMTP_CONNECT_TIMEOUT`, …), like `ACCESS_TOKEN_TTL`. The transport follow-up and
   the send flow (#165) receive it built.
3. **Validation precedence** (each failure reported at most once, never with a value):
   - a timeout that is not a positive integer reports only `<NAME>: not a positive integer`
     and is skipped by the `exceeds SMTP_TOTAL_TIMEOUT` check; an invalid
     `SMTP_TOTAL_TIMEOUT` skips both `exceeds` checks;
   - the comparison uses **effective** values (defaults applied): `SMTP_TOTAL_TIMEOUT=5`
     with `SMTP_CONNECT_TIMEOUT` unset fails with
     `SMTP_CONNECT_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT`.
4. **`zend.exception_ignore_args`:** there is no shared boot today (`public/index.php` and
   each `bin/*` script load `.env` themselves). One helper, `Pidgey\Infra\Runtime::harden()`
   (proposal), is called right after `.env` is loaded in `index.php` and every `bin/*` script
   (inside the command where the command loads `.env` itself). It sets the ini value outside
   `development` / `test`; an unset `PHP_ENV` counts as production. Tests: a unit test per
   `PHP_ENV`, and a test that scans `public/index.php` and `bin/*` and fails when an entry
   point does not call it.

## Solution

### Testing strategy

1. **Layers:**

   | Layer | What | How |
   | --- | --- | --- |
   | Unit | `ReplyParser` (table-driven: one data-provider row per rule of `send-flow.md#replies` and edge cases 4–6), `Deadline`, `SessionSettings` / `Container`, `ConfigValidator` cases, `Runtime::harden()`, `SecretString` | No I/O; `FrozenClock`. |
   | Unit | `Pidgey\Smtp\TlsContext` (proposal): a pure builder of the `stream_context` options | Asserts `verify_peer`, `verify_peer_name`, `peer_name`, SNI on for hostnames / off for IP literals, `crypto_method` TLS 1.2 \| 1.3, `cafile` — "no SNI" and "TLS 1.2+" are tested without a socket (edge case 12). |
   | Unit | `FakeSmtpStream` self-tests | Script mismatch fails the test; each injected failure. |
   | Integration | `SocketStream` × `FakeSmtpServer` | Timeouts, slowloris, wrong-name and untrusted certificates, IP SAN, pending bytes after `220`, close with no reply. |
   | Integration | `SocketStream` × Mailpit | `plain`, `starttls` upgrade and `ssl`, verified with the test CA; the test resolves the service host to an IP itself (the "checked IP + `host`" contract). |

2. **`FakeSmtpStream` time model:** a scripted reply may carry a `delay` that advances the
   shared `FrozenClock`; when the delay exceeds the timeout the read was given, the fake
   advances the clock by that timeout and returns a timeout failure. The per-reply budget,
   slowloris and the session deadline are then deterministic in the transport follow-up's
   unit tests.
3. **`FakeSmtpServer` scenario (JSON):** `mode` (`plain` / `starttls` / `ssl`), `cert`
   (`server` / `wrong-name` / `untrusted`), `steps[]`, each with:
   - `expect`: exact client line, or a prefix (e.g. `AUTH PLAIN `);
   - `reply`: raw bytes (malformed replies, bare LF possible);
   - `delayMs` before replying; `drip`: bytes sent one at a time (slowloris);
   - `then`: `close`, `hang`, `starttls` (handshake), `inject` (bytes right after `220`).

   A client line that does not match `expect` → mismatch on stderr, non-zero exit; teardown
   asserts the exit status, so a client mistake fails the test instead of becoming a
   misleading timeout. Startup: prints `PORT=<n>` once listening; the parent waits at most
   5 s. Teardown: `proc_terminate` + `proc_close`, plus a shutdown function so a fatal error
   leaves no orphan. Binary: `PHP_BINARY`.
4. **Real-wait timeouts:** 0.2–0.5 s timeouts passed as floats (edge case 14); loose bounds
   measured with `hrtime` (elapsed ≥ timeout and < timeout + 1 s) so a slow CI does not
   flake; about ten such tests, a few seconds in total.
5. **Guard tests:**
   - **No disabled verification:** a test scans `source/lib` and `source/tests` and fails on
     `'verify_peer' => false`, `'verify_peer_name' => false` or `allow_self_signed`.
   - **`SecretString`:** `(string)` throws; `serialize` / `clone` throw; `print_r` /
     `var_dump` show `[redacted]`; `json_encode` gives `{}`; a Monolog context logs the
     class name only (`CapturedLog`). `var_export` exposes private properties: a documented,
     accepted gap (never used by the app).

### Scope boundaries

- **Split decision:** the original #163 scope is split in two. This issue keeps the
  primitives and the socket stream; `SmtpTransport` (step sequence, no downgrade, injection
  guard, `AUTH` `PLAIN` / `LOGIN`, dot-stuffing, best-effort `QUIT`, `SmtpFailure` /
  `FailureCode` classification) and its reply-by-reply and end-to-end tests move to the
  follow-up issue #173 (SMTP transport session), which depends on this one.
- **Not in scope:** `SmtpTransport` and failure classification (follow-up issue); MIME
  building, `SendFlow`, `HostPolicy`, the HTTP mapping (send flow issue);
  `SMTP_ALLOW_PRIVATE_HOSTS` and `SMTP_TEST_*` (send flow and limit issues).

### Acceptance

- Every stream-level failure (connect failure, TLS failure, timeout, closed by peer) and
  every reply-parsing rule of `send-flow.md#replies` is covered by a unit or fake-server
  test; TLS verification is never disabled.
- `FakeSmtpStream` and `FakeSmtpServer` are usable by the follow-up transport issue without
  changes to their scenario format.
- The matching items of `implement-email/transport.md` (1, 2, 3, 6, 10) and `testing.md`
  are ticked in the same PR.

## Benefits

- The transport follow-up (#173) is reduced to the session state machine, on primitives
  and fakes that are already tested.
- TLS verification, timeouts and secret hygiene are enforced and guarded by tests in one
  place, before any endpoint can send email.
- The spec contradictions are resolved before code depends on them.
