# Backend Plan: Reduce complexity of the SMTP transport tests

Main plan: [plan.md](plan.md)

## Overview
Test-only refactor of `source/tests/unit/Smtp/SmtpTransportTest.php` (47 tests, 13 data providers, CCN 94) and `source/tests/integration/Smtp/SmtpTransportTest.php` (20 tests, 4 data providers, CCN 47). Every test method and data provider moves **verbatim** (same name, same body, same provider cases) into a smaller class; only `self::`/`$this->` references to moved constants and helpers change visibility. No file under `source/lib` changes.

## Context
- Complexity is measured with `darthjee/tingle_php_complexity:0.9.0` (warn=20 / error=40 / critical=60). File CCN is roughly the number of methods (tests + providers + helpers) plus branches, so the split has to move the helpers out of the test classes as well as the tests.
- `source/tests/support/` is autoloaded as `Pidgey\Tests\Support\` (`source/composer.json` `autoload-dev`) and is **not** scanned by PHPUnit (`phpunit.xml` only scans `tests/unit` and `tests/integration`), so abstract bases there are not picked up as test classes. Existing precedent: `IntegrationTestCase`, `RepositoryTestCase`, `SessionEndpointTestCase`, `SmtpConnectionEndpointTestCase`.
- PHPUnit attributes (`#[CoversClass]`, `#[UsesClass]`) are **not inherited**: every concrete split class repeats the full attribute list of the class it came from, so coverage attribution and `failOnRisky` behave as before.
- `composer lint` runs `phpmd` with `phpmd_unusedcode.xml` over `tests`: helpers in the base classes must be `protected` (not `private`), and every helper must still be used by at least one subclass.

## Steps

- [01 — Unit base class](backend/01-unit-base-class.md)
- [02 — Split the unit SmtpTransportTest](backend/02-split-unit-test.md)
- [03 — Integration base class](backend/03-integration-base-class.md)
- [04 — Split the integration SmtpTransportTest and move the coverage check](backend/04-split-integration-test.md)
- [05 — Verify](backend/05-verify.md)

## CI Checks
- `source`: `make tests` (runs `composer lint && composer tests` in the `tests` container; needs MySQL and the three Mailpit services) (CircleCI steps: `Run Lint`, `Tests`)

## Notes
- Do not rename tests or change data-provider keys: test count and names before/after must match one-to-one (plus none removed).
- If any resulting class (including a base class) still scores ≥ 20, split it further along the groups given in step 02/04, or move a cohesive helper group from a base class into a trait in `source/tests/support/`.
- Old files `source/tests/unit/Smtp/SmtpTransportTest.php` and `source/tests/integration/Smtp/SmtpTransportTest.php` are deleted at the end of their split steps.
