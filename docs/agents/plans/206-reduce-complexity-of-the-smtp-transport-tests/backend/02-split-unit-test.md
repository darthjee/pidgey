# Split the unit SmtpTransportTest

Replace `source/tests/unit/Smtp/SmtpTransportTest.php` with the classes below (namespace `Pidgey\Tests\Unit\Smtp`, all `final`, all `extends SmtpTransportTestCase`). Each one copies the full `#[CoversClass(SmtpTransport::class)]`, `#[CoversClass(SmtpSessionState::class)]` and `#[UsesClass(...)]` list of the original class, and only the `use` imports it needs. Tests and providers move verbatim; `self::CONST` and `$this->helper()` keep working through the base class.

| New class | Tests (and their data providers) |
|---|---|
| `SmtpTransportConnectionTest` | `testDeliversAMessageAndReturnsTheFinalReply`, `testDefaultStreamFactoryBuildsASocketStream`, `testStreamFactoryIsCalledOncePerSend`, `testGreetingOtherThan220Fails` (`greetings`), `testCloseBeforeTheGreetingIsAConnectionFailure`, `testGreetingTimeoutIsATimeout`, `testOpenAndGreetingShareTheConnectTimeout`, `testOpenFailureIsClassified` (`openFailures`), `testExpiredSessionDeadlineFailsWithoutIo` |
| `SmtpTransportEhloTest` | `testEhloUsesTheConfiguredName`, `testEhloFallsBackToTheLocalAddressLiteral` (`localAddresses`), `testEhloRejectionHasNoHeloFallback` (`ehloRejections`), `testPostTlsExtensionsReplaceThePreTlsList`, `testPostTlsEhloRejectionIsClassifiedAtEhlo` |
| `SmtpTransportTlsTest` | `testStartTlsUpgradesThenSendsEhloAgain`, `testStartTlsNotAdvertisedFailsAndQuits`, `testStartTlsNotAdvertisedFailsEvenWhenQuitFails`, `testStartTlsRefusedIsATlsFailureWithTheReply` (`startTlsRefusals`), `testUpgradeFailureClosesWithoutQuit` (`upgradeFailures`), `testImplicitTlsHandshakesAtOpenAndSendsNoStartTls` |
| `SmtpTransportAuthTest` | `testAuthPlainSendsTheInitialResponse`, `testAuthLoginAnswersBothPrompts`, `testRejectedPlainIsNotRetriedWithLogin`, `testLoginRejectedAtAnyStepIsAnAuthFailure` (`loginSteps`), `testNoSupportedMechanismNeverSendsTheCredentials` (`unsupportedAuth`), `testWithoutCredentialsNothingIsAuthenticated`, `testAnyReplyOtherThan235IsAnAuthFailure` (`authReplies`), `testAuthTimeoutIsATimeout`, `testMailFrom530IsAnAuthFailure`, `testMailFrom530AfterAuthenticationIsAnAuthFailure` |
| `SmtpTransportEnvelopeTest` | `testEnvelopeAndDataRejectionsAreClassified` (`envelopeRejections`), `testEveryRecipientGetsItsRcptTo`, `testMessageIsNormalizedAndDotStuffed` (`messages`), `testMultiLineFinalReplyIsReturnedWhole`, `testQuitNeverTurnsASentMessageIntoAFailure` (`quitOutcomes`) |
| `SmtpTransportProtocolErrorTest` | `testMalformedReplyIsAProtocolError` (`malformedReplies`), `testMalformedGreetingIsAProtocolError`, `testCloseWithNoReplyAfterTheGreetingIsAProtocolError`, `testWriteToAClosedPeerIsAProtocolError` |
| `SmtpTransportTimeoutTest` | `testReplyBeyondTheCommandTimeoutIsATimeout`, `testReplyJustWithinTheCommandTimeoutSucceeds`, `testSessionDeadlineCutsALaterReplyShort`, `testExpiredSessionDeadlineFailsTheNextWriteWithoutIo`, `testFinalReplyTimeoutIsATimeoutAtEndOfData` |
| `SmtpTransportSecretLeakTest` | `testAuthWriteFailureLeaksNoSecret`, `testSuccessWithCredentialsLeaksNoSecret`, `testCanaryCheckDetectsALeak` |

Before deleting the original file, check that all 47 test methods and 13 providers are accounted for, with nothing left behind and nothing duplicated (for example by diffing `grep -o 'function test[A-Za-z0-9]*'` before and after).

## Files to Change
- `source/tests/unit/Smtp/SmtpTransportTest.php` — deleted.
- `source/tests/unit/Smtp/SmtpTransportConnectionTest.php` — new.
- `source/tests/unit/Smtp/SmtpTransportEhloTest.php` — new.
- `source/tests/unit/Smtp/SmtpTransportTlsTest.php` — new.
- `source/tests/unit/Smtp/SmtpTransportAuthTest.php` — new.
- `source/tests/unit/Smtp/SmtpTransportEnvelopeTest.php` — new.
- `source/tests/unit/Smtp/SmtpTransportProtocolErrorTest.php` — new.
- `source/tests/unit/Smtp/SmtpTransportTimeoutTest.php` — new.
- `source/tests/unit/Smtp/SmtpTransportSecretLeakTest.php` — new.
