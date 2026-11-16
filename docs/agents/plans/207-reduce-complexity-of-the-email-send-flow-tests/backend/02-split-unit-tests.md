# Split the unit SendFlowTest
Create these `final` classes in `source/tests/unit/Email/`, each extending `SendFlowTestCase` and carrying the original `CoversClass`/`UsesClass` attributes. Move each test (and its provider) verbatim:

| Class | Tests |
|---|---|
| `SendFlowDeliveryTest` | `testSendsAndReturnsTheCleanedReply`, `testPassesTheCheckedIpHostPortModeEnvelopeAndMime`, `encryptions` + `testMapsTheEncryptionToTheTlsMode` |
| `SendFlowCredentialsTest` | `testSendsWithoutCredentialsWhenNoneAreStored`, `testSendsWithoutCredentialsWhenOnlyAUsernameIsStored`, `testDecryptsThePasswordForTheTransport`, `testACorruptPasswordIsUnreadable`, `testAPasswordUnderAnUnknownKeyIsUnreadable`, `testAPasswordBoundToAnotherRowIsUnreadable` |
| `SendFlowRefusalTest` | `testRefusesInsecureCredentialsBeforeAnythingElse`, `testRefusesADeniedHostBeforeDecrypting`, `testRefusesAHostResolvingToADeniedAddress`, `testAnUnresolvableHostIsAConnectionFailure`, `testAnUnsafeMessageIsALogicErrorAndNeverReachesTheTransport` |
| `SendFlowFailureTest` | `failures` + `testMapsEachTransportFailure`, `testRedactsTheEchoedPasswordInAFailureReply`, `testRedactsTheEchoedPasswordInASuccessReply` |
| `SendFlowPasswordWipeTest` | `testWipesThePasswordAfterASuccess`, `testWipesThePasswordAfterAFailure`, `testWipesThePasswordWhenAnUnexpectedErrorPropagates` |
| `SendFlowLoggingTest` | `testLogsOneInfoLineWhenSent`, `testLogsOneWarningLineWhenFailed`, `testLogsTheReplyOfAFailure`, `testLogsASendTimeFailure`, `testNeverLogsTheSubjectBodyUsernameOrPassword`, `testActorsGiveTheirLogContext` |

Total 26 test methods. Keep only the `use` imports each file needs. Delete `SendFlowTest.php`.

## Files to Change
- `source/tests/unit/Email/SendFlowDeliveryTest.php` — new.
- `source/tests/unit/Email/SendFlowCredentialsTest.php` — new.
- `source/tests/unit/Email/SendFlowRefusalTest.php` — new.
- `source/tests/unit/Email/SendFlowFailureTest.php` — new.
- `source/tests/unit/Email/SendFlowPasswordWipeTest.php` — new.
- `source/tests/unit/Email/SendFlowLoggingTest.php` — new.
- `source/tests/unit/Email/SendFlowTest.php` — deleted.
