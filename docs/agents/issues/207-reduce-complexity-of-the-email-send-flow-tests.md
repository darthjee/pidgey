# Issue: Reduce complexity of the email send flow tests

## Description

Part of #205. Bring both email send flow test classes below the warn threshold (CCN < 20) of `darthjee/tingle_php_complexity:0.9.0` (warn=20 / error=40 / critical=60).

| Status | CCN | Lines | Dup | File |
|---|---|---|---|---|
| ERROR | 51 | 232 | 101 | `source/tests/integration/Email/SendFlowTest.php` |
| ERROR | 40 | 212 | 60 | `source/tests/unit/Email/SendFlowTest.php` |

## Problem

Per-method complexity is already low (max 3). The file-level CCN is high for two reasons. Each class holds many tests and providers: the unit class has 26 test methods, 2 providers and 7 helpers, and the integration class has 16 test methods, 1 provider and about 20 helpers. Each class also repeats the same arrange code: building `SendFlow`, the cipher, `Message`, `SmtpConnection` and `SessionSettings`, plus the redaction and logging assertions.

## Expected Behavior

- Every test file produced or touched scores CCN < 20, and none is flagged WARN, ERROR or CRITICAL.
- Every existing test case and data-provider row is kept with the same assertions. Only file and class placement changes.
- The full PHPUnit suite (unit + integration) passes, `composer lint` passes, and coverage is unchanged.
- No production code under `source/lib` changes.

## Solution

Follow the pattern used for #206 (PR #211): abstract base test cases under `source/tests/support/`, with helper traits where needed to keep the base itself under CCN 20, and the original class split into focused classes. Repeat the `CoversClass`/`UsesClass` attributes on each new class. The existing `SendFlowTest.php` files are removed once they are empty.

### Unit: `source/tests/unit/Email/`

Base: `Pidgey\Tests\Support\SendFlowTestCase`. It holds the constants, `setUp` (CapturedLog and the call recorder), `flow()` with the recording fake `Transport`, `ok()`, `cipher()`, `message()`, `connection()`, `assertSendTimeFailure()` and `assertWiped()`.

| New class | Tests moved |
|---|---|
| `SendFlowDeliveryTest` | sends and returns the cleaned reply; passes the checked IP/host/port/mode/envelope/MIME; `encryptions` provider + TLS-mode mapping |
| `SendFlowCredentialsTest` | no credentials stored; only a username stored; decrypts the password; corrupt password, unknown key and password bound to another row are unreadable |
| `SendFlowRefusalTest` | insecure credentials refused first; denied host before decrypting; host resolving to a denied address; unresolvable host is a connection failure; an unsafe message is a logic error |
| `SendFlowFailureTest` | `failures` provider + maps each transport failure; redacts the echoed password in failure and success replies |
| `SendFlowPasswordWipeTest` | wipes the password after success, after failure, and when an unexpected error propagates |
| `SendFlowLoggingTest` | info line when sent; warning line when failed; logs the reply of a failure; logs a send-time failure; never logs subject/body/username/password; actors give their log context |

### Integration: `source/tests/integration/Email/`

Base: `Pidgey\Tests\Support\SendFlowIntegrationTestCase`. It holds the constants, `setUp` (CapturedLog and clearing the 3 Mailpit instances), `tearDown` (stopping the fake server), `flow()`, `cipher()`, `message()`, `connection()`, `fakeConnection()`, the settings builders, `startServer()`, and the shared assertions (`assertNoCanary`, `assertNoSecretIn`, `assertLogged`, `assertNotContacted`, `formatted`, `json`, `body`). If needed, move the fake-server step builders (`authenticatedSteps`, `skipUntilDot`, `authPayload`, `stablePrefix`, `mimeTemplate`, `listener`, `closedPort`) into a trait.

| New class | Tests moved |
|---|---|
| `SendFlowMailpitTest` | `mailpitDeliveries` provider + delivers through Mailpit; wrong credentials are an auth failure; no credentials on the auth instance; sends with the canary and redacts its echo |
| `SendFlowConnectionTest` | closed port is a connection failure; STARTTLS not advertised is a TLS failure; silent server is a timeout with the port hint |
| `SendFlowDialogueTest` | echoed AUTH payload is redacted; rejected recipient; temporary failure at the greeting; malformed reply is a protocol error |
| `SendFlowPolicyTest` | private host and private host name not allowed; insecure credentials refused; unreadable password refused; a sent outcome is not an error |

The #206 support classes (`SmtpTransportIntegrationTestCase` and its traits) are not reused. They are built around `SmtpTransport` directly and have different constants and signatures, for example `startServer` without a TLS mode. Share code with them only where a helper matches exactly.

### Verification

- Run `darthjee/tingle_php_complexity:0.9.0` against `source/tests` and check that every new or touched file is CCN < 20.
- Run `make tests` (docker, MySQL and Mailpit) and `composer lint`.
- The unit + integration test count for SendFlow is unchanged: 42 test methods (26 unit + 16 integration), with every provider row kept.
- Responsible agent: `backend`.

## Benefits

- Removes two ERROR entries from the #205 complexity report.
- Each concern (delivery, credentials, refusal, failure mapping, secret hygiene, logging, real-server behaviour) can be read and extended on its own.
