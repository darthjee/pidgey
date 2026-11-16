# Issue: Reduce complexity of the HTTP tests

## Description

Reduce the complexity of the three HTTP test files flagged by the `darthjee/tingle_php_complexity:0.9.0` report (part of Epic #205), without changing production code and without removing or weakening any test case.

| Status | CCN | Lines | Dup | File | Tests |
|---|---|---|---|---|---|
| CRITICAL | 67 | 211 | 99 | `source/tests/unit/Http/RequestTest.php` | 52 tests, 11 providers |
| ERROR | 48 | 167 | 125 | `source/tests/unit/Http/SmtpConnectionRulesTest.php` | 30 tests, 9 providers |
| ERROR | 40 | 209 | 25 | `source/tests/integration/Http/Handler/SmtpConnectionTestTest.php` | 18 tests, 1 provider |

## Problem

Per-method complexity is already low (max CCN 2–3); the file CCN is high because each class holds every test, data provider and helper for its subject:

- `RequestTest` covers method, path/query/uri, headers, cookies, body, media type, body size and JSON parsing of `Pidgey\Http\Request` in one class, and every test shares the `$_SERVER`/`$_COOKIE` save/restore plus the `jsonRequest()` helper.
- `SmtpConnectionRulesTest` covers `forCreate()` and `forUpdate()` (field rules, cross-field rules, credential/redirection rules, merging, debug redaction) in one class, sharing `createBody()`, `stored()`, `assertFailure()`, `failure()` and `longHost()`.
- `SmtpConnectionTestTest` covers delivery, recipient refusal, the per-organization rate limit/lease and send-time failures of `POST /smtp-connections/:id/test.json`, with nine private helpers (`member`, `plainConnection`, `path`, `recipients`, `limitRow`, `assertNoAttempt`, `testLines`, `retryAfter`, `sendWithHostPolicy`) and an env-variable save/restore in setUp/tearDown.

## Expected Behavior

- Every test file produced or touched scores CCN < 20 (below warn) in `darthjee/tingle_php_complexity:0.9.0`, including the new support classes.
- The test count is unchanged: 52 (Request) + 30 (SmtpConnectionRules) + 18 (SmtpConnectionTest handler) = 100, with the same data-provider cases.
- The full PHPUnit suite (unit + integration) and `composer lint` pass; coverage is unchanged.
- No production code under `source/lib` changes.

## Solution

Same pattern as #206 and #207: one abstract base TestCase per subject under `source/tests/support/` (namespace `Pidgey\Tests\Support`) holding the shared state and helpers (private members become protected), and the original class split by concern. `CoversClass`/`UsesClass` attributes are repeated on every concrete class (PHPUnit does not inherit them). The original file is deleted.

### 1. `RequestTest` (unit)

Base: abstract `RequestTestCase extends TestCase` — `$savedServer`/`$savedCookies` with setUp/tearDown restoring `$_SERVER`/`$_COOKIE`, and `jsonRequest()`.

New classes in `source/tests/unit/Http/` (namespace `Pidgey\Tests\Unit\Http`):

| Class | Tests |
|---|---|
| `RequestMethodTest` | method upper-cased, defaults to GET, falls back to the server global; uri falls back to the server global; options win over server globals (5) |
| `RequestPathTest` | `pathProvider`/testPath, path defaults to root, `nulPathProvider`/NUL rejected, NUL in the query not rejected, query raw / empty when absent / empty without uri / empty for unparsable uri, `rawUriProvider`/uri unchanged, uri defaults to root (10) |
| `RequestHeaderTest` | `headerNameProvider`/case-insensitive lookup, `unprefixedHeaderProvider`/content headers without prefix, Content-Type ignores a prefixed key, missing → null, missing → default, empty is not the default, value is raw, non-header server keys, headers option: header names, server keys, replaces server headers, keeps empty values (12) |
| `RequestCookieTest` | read from the global, missing → null, missing → default, empty is not the default, array cookie → default, cookies option replaces the global, lookup is case sensitive (7) |
| `RequestBodyTest` | body option replaces the input, body read from the input, `anyContentTypeProvider`/empty body, `jsonContentTypeProvider`/parses JSON content types, `unsupportedMediaTypeProvider`/415, media type checked before size (6) |
| `RequestBodySizeTest` | `oversizedContentLengthProvider`/413 without reading, 413 with an empty body, `acceptedContentLengthProvider`/ignored, body over the limit without Content-Length, body at the limit accepted (5) |
| `RequestJsonTest` | `badRequestBodyProvider`/400, parser errors carry no custom message, nesting within the depth limit, empty object, nested objects, unknown fields kept, json cached (7) |

### 2. `SmtpConnectionRulesTest` (unit)

Base: abstract `SmtpConnectionRulesTestCase extends TestCase` — constants `CANARY`, `STORED_BYTES`, `HOST_MESSAGE`; helpers `createBody()`, `stored()`, `assertFailure()`, `failure()`, and `protected static longHost()` (used by a provider).

| Class | Tests |
|---|---|
| `SmtpConnectionRulesCreateTest` | returns the validated fields, `noAuthenticationProvider`/without credentials, `acceptedHostProvider`/accepts the host, `unusualPairProvider`/unusual port–encryption pairs, credentials with ssl, requires every required field (6) |
| `SmtpConnectionRulesCreateFailureTest` | `createFieldFailureProvider`/field failure, `createCrossFieldProvider`/cross-field failure, cross-field rules run only once the field rules pass, failure never contains the password (4) |
| `SmtpConnectionRulesUpdateTest` | empty body changes nothing, null fields unchanged, ignores active and unknown fields, `requiredFieldProvider`/cannot clear a required field, rejects control characters in fromAddress, runs the field rules, merges the trimmed values, keeps the stored password bytes, debug output hides the password (9) |
| `SmtpConnectionRulesUpdateCredentialsTest` | `changeWithCredentialsProvider`/allowed changes, `redirectionProvider`/password required when the server changes, host change without credentials, setting none with credentials, adding credentials to a none connection, adding credentials with TLS, `mergedPairingProvider`/pairing on the merged state, only password without username, clearing both credentials, empty credentials without credentials are no change, legacy row with credentials and none (11) |

### 3. `SmtpConnectionTestTest` (integration)

Base: abstract `SmtpConnectionTestEndpointTestCase extends SmtpConnectionEndpointTestCase` — constants `NOT_FOUND`, `NOT_A_USER`, `MAX_ATTEMPTS`, `VARIABLES`; `$savedSettings`, `$mailpit`; setUp/tearDown for the env variables and Mailpit; helpers `member`, `plainConnection`, `path`, `recipients`, `limitRow`, `assertNoAttempt`, `testLines`, `retryAfter`, `sendWithHostPolicy`. If the base alone would reach CCN 20, move the lookup/assertion helpers (`recipients`, `limitRow`, `assertNoAttempt`, `testLines`, `retryAfter`) into a trait `SmtpConnectionTestAssertions`.

New classes in `source/tests/integration/Http/Handler/` (namespace `Pidgey\Tests\Integration\Http\Handler`), each `#[CoversClass(SmtpConnectionTestHandler::class)]`:

| Class | Tests |
|---|---|
| `SmtpConnectionTestDeliveryTest` | sends to the caller by default, to another approved user, to the stored address whatever the case, inactive connection can be tested, connection row unchanged, log lines carry no recipient in the operator line, anonymous caller unauthorized (7) |
| `SmtpConnectionTestRecipientTest` | `invalidRecipients`/invalid `to` refused before the lookup and the limit, non-approved or foreign address refused alike, unknown or foreign connection not found (3) |
| `SmtpConnectionTestLimitTest` | window refuses with Retry-After and does not count the refusal, held lease refuses first, expired lease ignored, limit is per organization (4) |
| `SmtpConnectionTestFailureTest` | send-time failures answered 409 and counted, host not allowed answered 409, SMTP failure carries the server reply and releases the lease, unexpected error releases the lease and writes no operator line (4) |

### Verification

- Run `darthjee/tingle_php_complexity:0.9.0` against `source/tests`: none of the new or touched files is at or above warn (20).
- `make tests` (docker: `composer lint && composer tests`, needs MySQL and Mailpit) passes, with 100 tests across the new classes.

## Benefits

- Removes one CRITICAL and two ERROR entries from the complexity report, moving Epic #205 forward.
- Each class reads as one concern of `Request`, `SmtpConnectionRules` or the test-email endpoint, so a failing test points at the behaviour it covers.
- Shared fixtures and assertions live in one base class per subject instead of being repeated.
