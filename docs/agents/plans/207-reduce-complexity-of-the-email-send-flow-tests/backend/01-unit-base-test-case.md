# Add the unit SendFlow base test case
Create `abstract class SendFlowTestCase extends TestCase` in namespace `Pidgey\Tests\Support`.

Move from `source/tests/unit/Email/SendFlowTest.php`, verbatim except visibility (`private` → `protected`):
- Constants `KEY`, `OTHER_KEY`, `PASSWORD`, `USERNAME`, `ORGANIZATION`, `CONNECTION`, `HOST`, `PUBLIC_IP`, `SUBJECT`, `BODY`, `RECIPIENT`.
- Properties `CapturedLog $log` and `array $calls` (with its phpdoc shape), and `setUp()`.
- Helpers `assertSendTimeFailure`, `assertWiped`, `flow` (with the anonymous recording `Transport`), `ok`, `cipher`, `message`, `connection`.

Providers stay in the test classes that use them. If the base scores CCN ≥ 20, move the assertion helpers into a trait `SendFlowAssertions` used by the base.

## Files to Change
- `source/tests/support/SendFlowTestCase.php` — new abstract base.
- `source/tests/support/SendFlowAssertions.php` — new trait, only if needed for CCN.
