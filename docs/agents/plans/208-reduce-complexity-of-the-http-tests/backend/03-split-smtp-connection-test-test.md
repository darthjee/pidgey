# Add the test-email endpoint base test case and split SmtpConnectionTestTest

Create `abstract class SmtpConnectionTestEndpointTestCase extends SmtpConnectionEndpointTestCase` in `source/tests/support/SmtpConnectionTestEndpointTestCase.php` holding, verbatim except visibility: constants `NOT_FOUND`, `NOT_A_USER`, `MAX_ATTEMPTS`, `VARIABLES`; properties `$savedSettings`, `$mailpit`; `setUp()`/`tearDown()` (calling the parent ones as today); helpers `member`, `plainConnection`, `path` (protected static), `recipients`, `limitRow`, `assertNoAttempt`, `testLines`, `retryAfter`, `sendWithHostPolicy`.

If the base scores CCN ≥ 20, move `recipients`, `limitRow`, `assertNoAttempt`, `testLines` and `retryAfter` into a trait `SmtpConnectionTestAssertions` (same folder) used by the base.

Move the tests of `source/tests/integration/Http/Handler/SmtpConnectionTestTest.php` into these classes in the same folder (each `final`, extending the base, with `#[CoversClass(SmtpConnectionTestHandler::class)]`), then delete the original:

| Class | Tests (and providers) |
|---|---|
| `SmtpConnectionTestDeliveryTest` | testSendsToTheCallerByDefault, testSendsToAnotherApprovedUserOfTheOrganization, testSendsToTheStoredAddressWhateverTheTypedCase, testAnInactiveConnectionCanBeTested, testTheConnectionRowIsUnchanged, testTheLogLinesCarryNoRecipientInTheOperatorLine, testAnAnonymousCallerIsUnauthorized (7) |
| `SmtpConnectionTestRecipientTest` | invalidRecipients + testAnInvalidToIsRefusedBeforeTheLookupAndTheLimit, testAnAddressThatIsNotAnApprovedOrganizationUserIsRefusedAlike, testAnUnknownOrForeignConnectionIsNotFound (3) |
| `SmtpConnectionTestLimitTest` | testTheWindowRefusesWithRetryAfterAndDoesNotCountTheRefusal, testAHeldLeaseRefusesFirst, testAnExpiredLeaseIsIgnored, testTheLimitIsPerOrganization (4) |
| `SmtpConnectionTestFailureTest` | testSendTimeFailuresAreAnswered409AndCounted, testAHostThatIsNotAllowedIsAnswered409, testASmtpFailureCarriesTheServerReplyAndReleasesTheLease, testAnUnexpectedErrorReleasesTheLeaseAndWritesNoOperatorLine (4) |

Total: 18 tests. The canary/post-condition checks of `SmtpConnectionEndpointTestCase` keep applying through inheritance.

## Files to Change
- `source/tests/support/SmtpConnectionTestEndpointTestCase.php` — new abstract base.
- `source/tests/support/SmtpConnectionTestAssertions.php` — new trait, only if needed for CCN.
- `source/tests/integration/Http/Handler/SmtpConnectionTest{Delivery,Recipient,Limit,Failure}Test.php` — new.
- `source/tests/integration/Http/Handler/SmtpConnectionTestTest.php` — deleted.
