# Stream interface, failures, TlsContext and FakeSmtpStream

Define the stream contract and its in-memory fake.

- `SmtpStream` interface, `StreamFailureKind` enum and `StreamFailure` per the shared
  contracts. `writeLine()`'s parameter carries `#[SensitiveParameter]`. `StreamFailure`
  messages are fixed per kind; the OpenSSL reason goes only to `reason()`.
- `TlsContext::options()`: detect IP literals with `filter_var(…, FILTER_VALIDATE_IP)`;
  `crypto_method` = `STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT`;
  never `allow_self_signed`, never `verify_peer*` false.
- `Pidgey\Tests\Support\FakeSmtpStream` per the contract:
  - an ordered script of expectations; each `SmtpStream` call consumes the next one and
    fails the test (PHPUnit `AssertionFailedError`) on a mismatch;
  - `reply()` delivers its raw bytes line by line through `readLine()`;
  - delays advance the shared `FrozenClock`; a delay beyond the read's
    `Deadline::remaining()` advances by that remaining time and throws
    `StreamFailure(Timeout)`;
  - `fail()` throws the given kind (after its delay); `expectUpgrade()` can be followed by
    `fail(UnexpectedData)` or `fail(Tls)`;
  - `localAddress()` returns a configurable IP (default `192.0.2.10`);
  - `assertDone()` fails when script entries remain.

Tests:

- `TlsContextTest`: hostname (SNI on, `SNI_server_name`), IPv4 and IPv6 literals (SNI off),
  `cafile` present / absent, `crypto_method` value, verification flags.
- `tests/unit/Support/FakeSmtpStreamTest.php`: mismatch fails, prefix match, delays and
  timeout conversion, each injected failure, `assertDone()`.
- Switch step 03's parser tests to `FakeSmtpStream` if they used a stub.

## Files to Change

- `source/lib/Smtp/SmtpStream.php`, `StreamFailure.php`, `StreamFailureKind.php`,
  `TlsContext.php` — new.
- `source/tests/support/FakeSmtpStream.php` — new.
- `source/tests/unit/Smtp/TlsContextTest.php`,
  `source/tests/unit/Support/FakeSmtpStreamTest.php` — new.
