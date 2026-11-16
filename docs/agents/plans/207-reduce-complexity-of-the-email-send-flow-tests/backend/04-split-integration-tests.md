# Split the integration SendFlowTest
Create these `final` classes in `source/tests/integration/Email/`, each extending `SendFlowIntegrationTestCase` and carrying the original `CoversClass`/`UsesClass` attributes. Move each test (and its provider) verbatim:

| Class | Tests |
|---|---|
| `SendFlowMailpitTest` | `mailpitDeliveries` + `testDeliversThroughMailpit`, `testWrongCredentialsAreAnAuthFailure`, `testNoCredentialsOnTheAuthInstanceRequireAuthentication`, `testSendsWithTheCanaryAndRedactsItsEcho` |
| `SendFlowConnectionTest` | `testClosedPortIsAConnectionFailure`, `testStartTlsNotAdvertisedIsATlsFailure`, `testSilentServerIsATimeoutWithThePortHint` |
| `SendFlowDialogueTest` | `testEchoedAuthPayloadIsRedacted`, `testRejectedRecipient`, `testTemporaryFailureAtTheGreeting`, `testMalformedReplyIsAProtocolError` |
| `SendFlowPolicyTest` | `testAPrivateHostIsNotAllowed`, `testAPrivateHostNameIsNotAllowed`, `testInsecureCredentialsAreRefused`, `testAnUnreadablePasswordIsRefused`, `testASentOutcomeIsNotAnError` |

Total 16 test methods. Keep only the `use` imports each file needs. Delete `SendFlowTest.php`. Leave `TestEmailLimitTest.php` untouched.

## Files to Change
- `source/tests/integration/Email/SendFlowMailpitTest.php` — new.
- `source/tests/integration/Email/SendFlowConnectionTest.php` — new.
- `source/tests/integration/Email/SendFlowDialogueTest.php` — new.
- `source/tests/integration/Email/SendFlowPolicyTest.php` — new.
- `source/tests/integration/Email/SendFlowTest.php` — deleted.
