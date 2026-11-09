# IP SAN fixture and FakeSmtpServer

- **Fixture:** in `generate.sh`, add `IP:127.0.0.1` to the `server` leaf SAN and update its
  header comment; run it and commit every regenerated file (new CA, new leaves). Mailpit in
  compose and CI reads the files from the checkout, so nothing else changes.
- **Server script** `source/tests/support/scripts/fake-smtp-server.php`:
  - reads the JSON scenario from a file path given as its first argument;
  - `stream_socket_server('tcp://127.0.0.1:0')` with an SSL context built from the fixture
    named by `cert` (`server` / `wrong-name` / `untrusted` → `<name>.pem` and
    `<name>-key.pem`); prints `PORT=<n>` and flushes once listening;
  - serves one session: `ssl` enables crypto right after accept; `then: starttls` enables it
    after sending that step's reply; `then: inject` writes `bytes` right after the reply,
    before any handshake;
  - for each step: wait for a client line when `expect` / `expectPrefix` is set (mismatch →
    message on stderr, exit 1), sleep `delayMs`, send `reply` (whole, or one byte at a time
    with ~50 ms gaps when `drip`), then apply `then` (`close`: close and exit 0; `hang`:
    keep the connection open without reading until killed);
  - exits 0 when the steps are done and the client closed; a handshake failure on the server
    side (wrong-name / untrusted tests) is expected and still exits 0.
- **`Pidgey\Tests\Support\FakeSmtpServer`**: writes the scenario to a temp file, starts the
  script with `proc_open([PHP_BINARY, script, file])`, reads `PORT=` within 5 s (else fails
  the test with stderr), exposes `host()` / `port()`; `stop()` terminates
  (`proc_terminate`), drains stderr and returns the exit code; `assertClean()`; a
  `register_shutdown_function` stops any server still running.

Tests:

- `tests/integration/Support/FakeSmtpServerTest.php`: start, read the port, connect with a
  raw socket, a plain scenario round trip, a mismatched line makes `assertClean()` fail,
  `stop()` after `hang` returns promptly.

## Files to Change

- `source/tests/fixtures/tls/generate.sh` — IP SAN, header comment.
- `source/tests/fixtures/tls/*.pem` — regenerated.
- `source/tests/support/scripts/fake-smtp-server.php` — new.
- `source/tests/support/FakeSmtpServer.php` — new.
- `source/tests/integration/Support/FakeSmtpServerTest.php` — new.
- `source/phpcs.xml` / `source/phpmd_unusedcode.xml` — only if the script needs an exclusion
  (it is a script, not a class).
