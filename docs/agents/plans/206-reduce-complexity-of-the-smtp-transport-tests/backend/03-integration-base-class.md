# Integration base class

Create `abstract class SmtpTransportIntegrationTestCase extends TestCase` in `source/tests/support/SmtpTransportIntegrationTestCase.php` (namespace `Pidgey\Tests\Support`) and move into it, from the integration `SmtpTransportTest`:

- The constants `CANARY`, `FIXTURE_CA`, `EHLO_NAME`, `TIMEOUT`, `SHORT_TIMEOUT`, `FROM`, `TO`, `DOT_LINE`, as `protected const`. `FIXTURE_CA` uses `__DIR__`, so recompute it for the new location: `__DIR__ . '/../fixtures/tls/ca.pem'` from `source/tests/support/`.
- The `$transport` and `$server` properties (`protected`), `setUp()` (builds `SmtpTransport(new SystemClock())` and clears the three Mailpit instances) and `tearDown()` (stops the fake server).
- Every private helper, changed to `protected`, bodies unchanged: `startServer`, `assertServerClean`, `envelopeSteps`, `sendToServer`, `sendTo`, `message`, `fakeSettings`, `mailpitSettings`, `canaryCredentials`, `fixtureCa`, `configuredCaFile`, `closedPort`, `failure`, `assertNoCanary`.

The `COVERAGE` constant and the `failureCodes` provider do **not** move here (see step 04). The base class has no test methods and no attributes, and must stay below CCN 20.

## Files to Change
- `source/tests/support/SmtpTransportIntegrationTestCase.php` — new abstract base (constants, transport/Mailpit setup, fake server teardown, helpers).
