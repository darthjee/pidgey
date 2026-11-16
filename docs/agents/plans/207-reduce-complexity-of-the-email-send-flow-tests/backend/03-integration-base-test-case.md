# Add the integration SendFlow base test case
Create `abstract class SendFlowIntegrationTestCase extends TestCase` in namespace `Pidgey\Tests\Support`.

Move from `source/tests/integration/Email/SendFlowTest.php` (`private` → `protected`):
- Constants `CANARY`, `USERNAME`, `KEY`, `ORGANIZATION`, `CONNECTION`, `FROM`, `TO`, `SUBJECT`, `TEXT`, `HTML`, `EHLO_NAME`, `FIXTURE_CA` (re-anchored to `__DIR__ . '/../fixtures/tls/ca.pem'`), `TIMEOUT`, `SHORT_TIMEOUT`, `TIMESTAMP`.
- Properties `$log`, `$server`; `setUp()` (CapturedLog + clearing the 3 Mailpit instances) and `tearDown()` (stop the fake server).
- Builders: `flow`, `cipher`, `message`, `connection`, `fakeConnection`, `fakeSettings`, `mailpitSettings`, `startServer`.
- Assertions/formatting: `formatted`, `json`, `body`, `assertNoCanary`, `assertNoSecretIn`, `assertLogged`, `assertNotContacted`.

Put the fake-server step builders `authenticatedSteps`, `skipUntilDot`, `mimeTemplate`, `stablePrefix`, `authPayload`, `listener`, `closedPort` into trait `SendFlowServerSteps` used by the base. If the base still scores CCN ≥ 20, also move the assertion/formatting helpers into a trait `SendFlowIntegrationAssertions`.

## Files to Change
- `source/tests/support/SendFlowIntegrationTestCase.php` — new abstract base.
- `source/tests/support/SendFlowServerSteps.php` — new trait.
- `source/tests/support/SendFlowIntegrationAssertions.php` — new trait, only if needed for CCN.
