# Backend Plan: Reduce complexity of the email send flow tests

Main plan: [plan.md](plan.md)

## Overview
Same pattern as #206 (PR #211, see `source/tests/support/SmtpTransportTestCase.php` and `SmtpTransportIntegrationTestCase.php`): move shared constants, `setUp`/`tearDown` and helpers into abstract base classes (plus helper traits when a base would itself reach CCN ≥ 20), then move each test into a class focused on one concern. Delete the original `SendFlowTest.php` files once empty.

## Context
- Unit class: 26 test methods, providers `encryptions` and `failures`, helpers `assertSendTimeFailure`, `assertWiped`, `flow` (recording anonymous `Transport` + stub `HostResolver`), `ok`, `cipher`, `message`, `connection`. State: `CapturedLog $log`, `array $calls`.
- Integration class: 16 test methods, provider `mailpitDeliveries`, ~20 helpers (`authenticatedSteps`, `skipUntilDot`, `mimeTemplate`, `stablePrefix`, `authPayload`, `startServer`, `fakeConnection`, `flow`, `cipher`, `message`, `connection`, `fakeSettings`, `mailpitSettings`, `formatted`, `json`, `body`, `assertNoCanary`, `assertNoSecretIn`, `assertLogged`, `listener`, `assertNotContacted`, `closedPort`). State: `CapturedLog $log`, `?FakeSmtpServer $server`.
- Support classes autoload as `Pidgey\Tests\Support\` from `source/tests/support/` (PSR-4, `composer.json` autoload-dev). Test namespaces stay `Pidgey\Tests\Unit\Email` / `Pidgey\Tests\Integration\Email`.
- The #206 SMTP transport support classes are NOT reused (different constants and signatures, e.g. `startServer` without TLS mode).

## Steps

- [01 — Add the unit SendFlow base test case](backend/01-unit-base-test-case.md)
- [02 — Split the unit SendFlowTest](backend/02-split-unit-tests.md)
- [03 — Add the integration SendFlow base test case](backend/03-integration-base-test-case.md)
- [04 — Split the integration SendFlowTest](backend/04-split-integration-tests.md)
- [05 — Verify complexity, tests and lint](backend/05-verify.md)

## CI Checks
- `source`: `make tests` (runs `composer lint && composer tests` in docker; integration needs MySQL and Mailpit) — CI job runs `composer lint` and the PHPUnit suites.
- Complexity: `darthjee/tingle_php_complexity:0.9.0` against `source/tests`.

## Notes
- Moving tests must not change assertions, provider rows or test method names.
- Each new class repeats the `CoversClass`/`UsesClass` attributes the original had (keep only those actually needed is NOT required; copying the full set is safest to avoid PHPUnit "not a valid target"/risky warnings under `failOnRisky`/`failOnWarning`). If PHPUnit warns that a `UsesClass` is unused, that is not an error; do not drop `CoversClass(SendFlow::class)`.
- Private constants/helpers referenced from subclasses become `protected`.
- Paths using `__DIR__` (integration `FIXTURE_CA = __DIR__ . '/../../fixtures/tls/ca.pem'`) must be re-anchored when moved into `source/tests/support/` → `__DIR__ . '/../fixtures/tls/ca.pem'`.
