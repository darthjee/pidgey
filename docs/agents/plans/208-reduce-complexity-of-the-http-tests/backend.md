# Backend Plan: Reduce complexity of the HTTP tests

Main plan: [plan.md](plan.md)

## Overview
Same pattern as #206 (PR #211) and #207 (PR #212, see `source/tests/support/SendFlowTestCase.php`): move shared constants, `setUp`/`tearDown` and helpers into an abstract base class per subject (plus a helper trait when a base would itself reach CCN ≥ 20), then move each test into a class focused on one concern. Delete each original file once empty.

## Context
- `RequestTest` (unit, `Pidgey\Tests\Unit\Http`): 52 tests, 11 providers, `$savedServer`/`$savedCookies` with setUp/tearDown restoring `$_SERVER`/`$_COOKIE`, helper `jsonRequest()`.
- `SmtpConnectionRulesTest` (unit, `Pidgey\Tests\Unit\Http`): 30 tests, 9 providers, constants `CANARY`, `STORED_BYTES`, `HOST_MESSAGE`, helpers `createBody`, `stored`, `assertFailure`, `failure`, static `longHost` (called from `createFieldFailureProvider`).
- `SmtpConnectionTestTest` (integration, `Pidgey\Tests\Integration\Http\Handler`, extends `SmtpConnectionEndpointTestCase`): 18 tests, provider `invalidRecipients`, constants `NOT_FOUND`, `NOT_A_USER`, `MAX_ATTEMPTS`, `VARIABLES`, state `$savedSettings`, `$mailpit`, env save/restore in setUp/tearDown, helpers `member`, `plainConnection`, `path` (static), `recipients`, `limitRow`, `assertNoAttempt`, `testLines`, `retryAfter`, `sendWithHostPolicy`.
- Support classes autoload as `Pidgey\Tests\Support\` from `source/tests/support/` (PSR-4, autoload-dev).
- No existing class name collides with the new ones (`unit/Model/SmtpConnectionTest.php` is a different name).

## Steps

- [01 — Add the Request base test case and split RequestTest](backend/01-split-request-test.md)
- [02 — Add the SmtpConnectionRules base test case and split SmtpConnectionRulesTest](backend/02-split-smtp-connection-rules-test.md)
- [03 — Add the test-email endpoint base test case and split SmtpConnectionTestTest](backend/03-split-smtp-connection-test-test.md)
- [04 — Verify complexity, tests and lint](backend/04-verify.md)

## CI Checks
- `source`: `make tests` (runs `composer lint && composer tests` in docker; integration needs MySQL and Mailpit).
- Complexity: `darthjee/tingle_php_complexity:0.9.0` against `source/tests`.

## Notes
- Moving tests must not change assertions, provider rows or test method names.
- Each new concrete class repeats the full `CoversClass`/`UsesClass` attribute set of its original (PHPUnit does not inherit attributes from the abstract base).
- Private constants/helpers referenced from subclasses become `protected` (static ones `protected static`).
- Providers stay in the test class that uses them; `longHost` must be `protected static` on the base so the provider can call `self::longHost()`/`static::longHost()`.
- In the integration base, `testLines()` is a protected helper, not a test (PHPUnit only collects public `test*` methods); keep its name.
