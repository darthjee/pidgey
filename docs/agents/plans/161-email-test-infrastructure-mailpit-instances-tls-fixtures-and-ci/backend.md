# Backend Plan: Email test infrastructure: Mailpit instances, TLS fixtures and CI

Main plan: [plan.md](plan.md)

## Shared contracts

The backend relies on:

- the `TEST_MAILPIT_*` keys (form `host:smtpPort:apiPort`), set in compose and CI;
- the instance modes and the auth literal, from
  [plan.md#instances](plan.md#instances);
- `tests/fixtures/tls/ca.pem`, relative to `source/`, which is the working directory of
  the tests.

The backend produces `Mailpit::AUTH_USER = 'pidgey-test'` and
`Mailpit::AUTH_PASSWORD = 'pidgey-test-password'`.

## Implementation Steps

### Step 1 — `Pidgey\Tests\Support\Mailpit`

Create `source/tests/support/Mailpit.php`, a `final` test support class.

- `public static function plain(): self`, `starttls(): self` and `ssl(): self` read
  `TEST_MAILPIT_PLAIN`, `TEST_MAILPIT_STARTTLS` and `TEST_MAILPIT_SSL` through
  `Pidgey\Infra\Env`.
  - A missing or malformed key (not exactly `host:int:int`) calls
    `PHPUnit\Framework\Assert::fail()` with a message naming the key. It never skips.
- Accessors `host()`, `smtpPort()` and `apiPort()`, plus the constants `AUTH_USER` and
  `AUTH_PASSWORD`.
- `clear(): void` sends `DELETE /api/v1/messages`.
- `messages(): array` reads `GET /api/v1/messages` and returns the decoded `messages` list.
- `message(string $id): array` reads `GET /api/v1/message/{id}` and returns the decoded
  body.
- HTTP goes through `file_get_contents` with a stream context: `method`, a 5-second
  `timeout` and `ignore_errors`.
  - A non-2xx status or invalid JSON fails the test with the method, URL and status.
  - There is no new Composer dependency.
- Add the class to the `testing/backend.md` support table only if its description changes
  (it is already listed).

### Step 2 — Smoke test

Create `source/tests/integration/Support/MailpitTest.php`, with
`#[CoversClass(Mailpit::class)]`. It needs no database, so it extends `TestCase`, not
`IntegrationTestCase`.

It uses a minimal private SMTP helper (private methods, or a private nested helper in the
test file):

- connect with `stream_socket_client` (`tcp://` for plain and STARTTLS, `ssl://` for
  implicit TLS), with a 5-second timeout;
- the stream context sets `ssl.verify_peer=true`, `verify_peer_name=true`,
  `cafile=<SMTP_CA_FILE>` and `peer_name=<host>`;
- read multi-line replies and assert the codes;
- STARTTLS through `stream_socket_enable_crypto(..., STREAM_CRYPTO_METHOD_TLS_CLIENT)`.

Tests:

- **Plain:** `clear()`, then send `EHLO`, `MAIL FROM`, `RCPT TO`, `DATA` and a minimal
  message with a unique subject, then `QUIT`. Then `messages()` has exactly one message, and
  `message($id)` returns that subject and recipient.
- **STARTTLS, right credentials:** `EHLO`, `STARTTLS` (`220`), TLS verified, `EHLO`, then
  `AUTH PLAIN` with the constants returns `235`.
- **STARTTLS, wrong password:** the same, but `AUTH PLAIN` returns `535`.
- **SSL, right / wrong:** the same two cases over implicit TLS.
- **Missing key:** with the env key unset (save and restore around the call),
  `Mailpit::plain()` fails with a message naming `TEST_MAILPIT_PLAIN`. Assert it through
  `AssertionFailedError`.

Certificate verification is never disabled.

## Files to Change

- `source/tests/support/Mailpit.php` — new support class.
- `source/tests/integration/Support/MailpitTest.php` — new smoke test.

## CI Checks

- `source`: `make tests` (`composer lint && composer tests`; CI jobs `backend_checks` and
  `backend_tests`).

## Notes

- `Env::get` semantics decide how "unset" is simulated in the missing-key test. Use
  `putenv` and restore in `finally`/`tearDown`.
- The phpmd unused-code gate and phpcs apply to `tests/` as they do to `lib/`; follow the
  existing support classes' style.
- `SMTP_CA_FILE` is read directly from the env in the test (`Env::get`), because no
  production class reads it yet.
