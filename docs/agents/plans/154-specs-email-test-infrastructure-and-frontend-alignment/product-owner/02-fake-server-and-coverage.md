# Fake SMTP server and required coverage

Write the fake server and the required coverage (issue decisions 4 and 6).

**Fake server**, in `email/testing.md`, plus the support classes in `testing/backend.md`:

- **`Pidgey\Tests\Support\FakeSmtpStream`:**
  - in memory, implementing the stream abstraction of `implement-email/transport.md#stream`;
  - scripted as an ordered list of expected client lines and server replies;
  - can inject the failures: timeout, closed by the peer, TLS failure, pending bytes after
    `STARTTLS`;
  - used with a frozen clock for the session deadline.
- **`Pidgey\Tests\Support\FakeSmtpServer`:**
  - a PHP `stream_socket_server` script started as a child process (`proc_open`), listening
    on `127.0.0.1` with an ephemeral port that it prints on stdout;
  - reads a JSON scenario and serves one session: plain, STARTTLS or implicit TLS, with the
    fixture certificates;
  - covers accept-and-never-answer, `tls` without `STARTTLS` advertised, no supported
    `AUTH`, multi-line, malformed and `4xx` replies, and wrong-name or untrusted
    certificates;
  - killed in teardown.
- **Other support classes:** `Pidgey\Tests\Support\Mailpit` (API client: clear, list, read)
  and `Pidgey\Tests\Support\CapturedLog` (a Monolog `TestHandler` injected into the
  container's logger).

**Required coverage**, as a list in `email/testing.md` that the backend issues accept
against:

- **Encryption:** the testable rules of `email/password-encryption.md`, which already cover
  round-trip, a fresh nonce, tampering, the wrong key, an associated-data mismatch and
  previous keys. Link them; don't copy.
- **Canary password:** on every success and failure path, the canary password and its base64
  forms (alone and inside the `AUTH` payload) never appear in the response body, in any
  captured log record (message, context, exception trace) or in any exception message.
- **Transport:**
  - reply-by-reply unit tests for every step of `send-flow.md#smtp-session`;
  - the whole `SMTP_*` mapping table of `implement-email/transport.md#failure`, end to end
    through Mailpit or the fake server.
- **Concurrency:**
  - Concurrent `activate`: a child process holds the organization lock. The test proves the
    second request waits and exactly one connection ends active.
  - The unique index rejects a second active row inserted directly.
  - A `PATCH` racing an `activate` never changes `active`.
- **Limits and host check:**
  - The test-email limit (window, lease, `Retry-After`) runs with a frozen clock.
  - The host check is unit-tested with an injectable resolver; one integration test runs
    with `SMTP_ALLOW_PRIVATE_HOSTS=false` against `127.0.0.1` and expects `409`.
- **Endpoints:** the Testable rules of `api/endpoints/pidgey-admin/smtp-connections.md`.

In `testing/backend.md`:

- list the new support classes and the child-process fixtures (the server script and the
  lock holder) under the support and fixtures folders;
- note that the integration suite now also needs the three test Mailpit instances.

## Files to Change

- `docs/agents/specs/email/testing.md` — the fake server and the required coverage list.
- `docs/agents/specs/testing/backend.md` — the support classes, child-process fixtures and
  integration dependencies.
