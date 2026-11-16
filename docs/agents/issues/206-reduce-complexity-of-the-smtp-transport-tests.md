# Issue: Reduce complexity of the SMTP transport tests

## Description

Reduce the complexity of the SMTP transport tests.

| Status | CCN | Lines | Dup | File |
|---|---|---|---|---|
| CRITICAL | 94 | 545 | 251 | `source/tests/unit/Smtp/SmtpTransportTest.php` |
| ERROR | 47 | 198 | 181 | `source/tests/integration/Smtp/SmtpTransportTest.php` |

### Suggested split

- **Unit** (`tests/unit/Smtp/`, 47 tests): greeting/open, EHLO/STARTTLS/implicit TLS, AUTH, envelope/DATA/message normalization, timeouts/protocol errors/QUIT, secret-leak checks.
- **Integration** (`tests/integration/Smtp/`, 20 tests): Mailpit delivery/auth, TLS (bad certificates, STARTTLS, injected bytes), fake-server failures (auth, malformed reply, RCPT/DATA rejections), timeouts and connection failures.

## Problem

The two SMTP transport test classes exceed the complexity thresholds of `darthjee/tingle_php_complexity:0.9.0` (warn=20 / error=40 / critical=60): the unit class is CRITICAL (CCN 94) and the integration class is ERROR (CCN 47). Per-method complexity is low; the file-level score comes from many tests and data providers per class plus heavily duplicated arrange code. Parent: #205.

## Expected Behavior

- Every test file produced or touched scores CCN < 20 (below warn) in `darthjee/tingle_php_complexity:0.9.0`.
- The number of tests in this area is the same or higher; the full PHPUnit suite (unit + integration) passes; coverage is unchanged.
- Responsible agent: `backend`.
- The failure-code coverage check still fails when a `FailureCode` has no reaching test, or a referenced test is renamed or moved.
- The secret-leak (canary) checks keep their exact assertions.

## Solution

- Parent: #205 (test-suite complexity reduction, measured with `darthjee/tingle_php_complexity:0.9.0`, thresholds warn=20 / error=40 / critical=60).
- Per-method complexity is already low; file-level CCN is high because each class holds many tests and data providers, plus duplicated arrange code.
- Split each class into smaller classes focused on one concern, and move duplicated arrange helpers into area-specific traits or base classes under `source/tests/support/` (reuse existing support classes where they fit).
- No production code changes; no test case is removed or weakened.

### Shared helpers: abstract base TestCases

- Add an abstract unit base class (e.g. `source/tests/support/SmtpTransportTestCase.php`) holding the `FrozenClock` `setUp`, the constants (`CANARY`, `USERNAME`, `HOST`, `CA_FILE`, `EHLO`, `MESSAGE`), the `#[UsesClass]`-relevant scaffolding and the dialogue helpers (`stream`, `happyStream`, `greet`, `ehlo`, `login`, `envelopeAndData`, `upTo`, `quit`, `target`, `settings`, `envelope`, `send`, `credentials`, `plainPayload`, `leaks`).
- Add an abstract integration base class (e.g. `source/tests/support/SmtpTransportIntegrationTestCase.php`) holding `setUp` (transport + Mailpit clear), `tearDown` (fake server stop), constants and helpers (`startServer`, `assertServerClean`, `envelopeSteps`, `sendToServer`, `sendTo`, `message`, `fakeSettings`, `mailpitSettings`, `canaryCredentials`, `fixtureCa`, `configuredCaFile`, `closedPort`, `failure`, `assertNoCanary`).
- Each split class extends the matching base and keeps its own `#[CoversClass]`/`#[UsesClass]` attributes, so coverage metadata stays accurate (PHPUnit attributes are not inherited).
- The base classes themselves must also stay below the warn threshold; if one grows too large, move a cohesive group of helpers into a trait.

### Failure-code coverage check

`testEveryFailureCodeIsReachedByATestOfThisFile` currently asserts `method_exists($this, …)` against a `COVERAGE` map of test names in the same class, which breaks once the class is split. Move it into a small dedicated integration test (e.g. `tests/integration/Smtp/FailureCodeCoverageTest.php`) whose `COVERAGE` map points every `FailureCode` to `[SplitClass::class, 'testName']` and asserts `method_exists` on that class. It still fails when a new `FailureCode` is added without a reaching test, or when a referenced test is renamed or moved.

### Security: secret-leak checks

The canary checks (`testCanaryCheckDetectsALeak`, `testSuccessWithCredentialsLeaksNoSecret`, `testAuthWriteFailureLeaksNoSecret`, the integration `assertNoCanary` calls) must keep their exact assertions after the split; the canary constants and `leaks`/`assertNoCanary` helpers live in the base classes so every split class uses the same marker.

### Testing strategy

- Compare the PHPUnit test count for `tests/unit/Smtp` and `tests/integration/Smtp` before and after (including data-provider cases); it must be the same or higher (the coverage check keeps its 7 cases).
- Run the full suite (unit + integration, which needs Mailpit and the fake SMTP server) and confirm `lib/Smtp` coverage is unchanged.
- Rerun `darthjee/tingle_php_complexity:0.9.0` on `source/tests`: every new or touched file, including the base classes, below CCN 20.

## Benefits

- Smaller, focused test classes that are easier to navigate and extend.
- One place for the SMTP dialogue and server helpers, removing duplicated arrange code.
- The SMTP area of the test suite passes the complexity check.
