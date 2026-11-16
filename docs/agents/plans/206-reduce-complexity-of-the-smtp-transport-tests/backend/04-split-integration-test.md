# Split the integration SmtpTransportTest and move the coverage check

Replace `source/tests/integration/Smtp/SmtpTransportTest.php` with the classes below (namespace `Pidgey\Tests\Integration\Smtp`, all `final`). Each one copies the original class's full `#[CoversClass]`/`#[UsesClass]` list, and tests and providers move verbatim.

| New class | Extends | Tests (and their data providers) |
|---|---|---|
| `SmtpTransportMailpitTest` | `SmtpTransportIntegrationTestCase` | `testMailpitDeliversTheMessage` (`mailpitDeliveries`), `testMailpitWrongPasswordIsAnAuthFailure`, `testMailpitWithoutCredentialsIsAnAuthFailureAtMailFrom`, `testImplicitTlsAgainstThePlainPortIsATlsFailure`, `testPlainAgainstTheSslPortIsATimeout` |
| `SmtpTransportTlsTest` | `SmtpTransportIntegrationTestCase` | `testStartTlsNotAdvertisedIsATlsFailure`, `testInjectedBytesBeforeTheHandshakeAreATlsFailure`, `testImplicitTlsWithABadCertificateIsATlsFailure` and `testStartTlsWithABadCertificateIsATlsFailure` (`badCertificates`), `testStartTlsSessionOnTheFakeServerDelivers` |
| `SmtpTransportFakeServerTest` | `SmtpTransportIntegrationTestCase` | `testMultiLineFinalReplyIsReturnedWhole`, `testAuthFailuresOnTheFakeServer` (`authFailures`), `testMalformedReplyIsAProtocolError`, `testTemporaryFailureAtRcptTo`, `testRejectedMessageAtEndOfData` |
| `SmtpTransportConnectionTest` | `SmtpTransportIntegrationTestCase` | `testHangingGreetingIsATimeout`, `testHangingReplyIsBoundByTheCommandTimeout`, `testCloseBeforeTheGreetingIsAConnectionFailure`, `testClosedPortIsAConnectionFailure` |
| `FailureCodeCoverageTest` | `TestCase` | `testEveryFailureCodeIsReachedByATestOfThisFile` → renamed `testEveryFailureCodeIsReachedByAnSmtpTransportTest` (`failureCodes`) |

`FailureCodeCoverageTest` keeps the `failureCodes` provider unchanged (one case per `FailureCode::cases()`), and holds a `COVERAGE` constant mapping each code value to a `[class-string, method]` pair:

- `SMTP_CONNECTION_FAILED` → `[SmtpTransportConnectionTest::class, 'testCloseBeforeTheGreetingIsAConnectionFailure']`
- `SMTP_TLS_FAILED` → `[SmtpTransportTlsTest::class, 'testStartTlsNotAdvertisedIsATlsFailure']`
- `SMTP_AUTH_FAILED` → `[SmtpTransportMailpitTest::class, 'testMailpitWrongPasswordIsAnAuthFailure']`
- `SMTP_REJECTED` → `[SmtpTransportFakeServerTest::class, 'testRejectedMessageAtEndOfData']`
- `SMTP_TEMPORARY_FAILURE` → `[SmtpTransportFakeServerTest::class, 'testTemporaryFailureAtRcptTo']`
- `SMTP_PROTOCOL_ERROR` → `[SmtpTransportFakeServerTest::class, 'testMalformedReplyIsAProtocolError']`
- `SMTP_TIMEOUT` → `[SmtpTransportConnectionTest::class, 'testHangingGreetingIsATimeout']`

The test asserts `assertArrayHasKey($code->value, self::COVERAGE)`, then `assertTrue(method_exists(...self::COVERAGE[$code->value]))`. It still fails when a new `FailureCode` has no entry, or when a referenced test is renamed or moved. It needs `#[CoversNothing]` or the same coverage attributes as before, whichever keeps `failOnRisky` green. It does not extend the integration base, so it doesn't clear Mailpit.

Renaming the self-check test is the only rename allowed, because the old name ("this file") would be false after the move.

## Files to Change
- `source/tests/integration/Smtp/SmtpTransportTest.php` — deleted.
- `source/tests/integration/Smtp/SmtpTransportMailpitTest.php` — new.
- `source/tests/integration/Smtp/SmtpTransportTlsTest.php` — new.
- `source/tests/integration/Smtp/SmtpTransportFakeServerTest.php` — new.
- `source/tests/integration/Smtp/SmtpTransportConnectionTest.php` — new.
- `source/tests/integration/Smtp/FailureCodeCoverageTest.php` — new.
