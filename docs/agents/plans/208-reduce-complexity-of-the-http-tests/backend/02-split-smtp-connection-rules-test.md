# Add the SmtpConnectionRules base test case and split SmtpConnectionRulesTest

Create `abstract class SmtpConnectionRulesTestCase extends TestCase` in `source/tests/support/SmtpConnectionRulesTestCase.php` holding, verbatim except visibility: constants `CANARY`, `STORED_BYTES`, `HOST_MESSAGE` (protected), helpers `createBody`, `stored`, `assertFailure`, `failure` (protected) and `longHost` (protected static).

Move the tests of `source/tests/unit/Http/SmtpConnectionRulesTest.php` into these classes in `source/tests/unit/Http/` (each `final`, extending the base, with the original `CoversClass(SmtpConnectionRules::class)`, `CoversClass(SmtpConnectionChange::class)` and `UsesClass` attributes), then delete the original:

| Class | Tests (and providers) |
|---|---|
| `SmtpConnectionRulesCreateTest` | testCreateReturnsTheValidatedFields, noAuthenticationProvider + testCreateWithoutCredentials, testCreateRequiresEveryRequiredField, acceptedHostProvider + testCreateAcceptsTheHost, unusualPairProvider + testCreateAcceptsUnusualPortEncryptionPairs, testCreateCredentialsWithSsl (6) |
| `SmtpConnectionRulesCreateFailureTest` | createFieldFailureProvider + testCreateFieldFailure, createCrossFieldProvider + testCreateCrossFieldFailure, testCrossFieldRulesRunOnlyOnceTheFieldRulesPass, testCreateFailureNeverContainsThePassword (4) |
| `SmtpConnectionRulesUpdateTest` | testUpdateWithAnEmptyBodyChangesNothing, testUpdateNullFieldsAreUnchanged, testUpdateIgnoresActiveAndUnknownFields, requiredFieldProvider + testUpdateCannotClearARequiredField, testUpdateRejectsControlCharactersInTheFromAddress, testUpdateRunsTheFieldRules, testUpdateMergesTheTrimmedValues, testUpdateKeepsTheStoredPasswordBytesWithoutANewPassword, testChangeDebugOutputHidesThePassword (9) |
| `SmtpConnectionRulesUpdateCredentialsTest` | changeWithCredentialsProvider + testUpdateAllowedOnAConnectionWithCredentials, redirectionProvider + testUpdateRequiresThePasswordWhenTheServerChanges, testUpdateChangingTheHostWithoutCredentialsNeedsNoPassword, testUpdateSettingNoneOnAConnectionWithCredentials, testUpdateAddingCredentialsToANoneConnection, testUpdateAddingCredentialsWithTls, mergedPairingProvider + testUpdatePairingRunsOnTheMergedState, testUpdateOnlyPasswordOnAConnectionWithoutUsername, testUpdateClearingBothCredentials, testUpdateEmptyCredentialsOnAConnectionWithoutCredentialsAreNoChange, testUpdateOnALegacyRowWithCredentialsAndNoneFails (11) |

Total: 30 tests.

## Files to Change
- `source/tests/support/SmtpConnectionRulesTestCase.php` — new abstract base.
- `source/tests/unit/Http/SmtpConnectionRules{Create,CreateFailure,Update,UpdateCredentials}Test.php` — new.
- `source/tests/unit/Http/SmtpConnectionRulesTest.php` — deleted.
