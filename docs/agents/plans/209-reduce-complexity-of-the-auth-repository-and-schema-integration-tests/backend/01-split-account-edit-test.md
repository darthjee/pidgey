# Add the AccountEdit base test case and split AccountEditTest

Create `abstract class AccountEditTestCase extends SessionEndpointTestCase` in `source/tests/support/AccountEditTestCase.php` holding the constants, the static passwords/digest and `setUpBeforeClass`, the state, `setUp`, and the helpers `service`, `commitConcurrently`, `user`, `reload`, `wrongPassword`.

To keep the base below CCN 20:
- Replace the anonymous clock with `new FrozenWallClock(new DateTimeImmutable(self::NOW, new DateTimeZone('UTC')))` (same instant).
- Replace the anonymous verifier with a named `final class CountingPasswordVerifier implements PasswordVerifier` in `source/tests/support/` (constructor takes the `Closure(): void` run before each check; `verify()` calls it then delegates to `new Passwords()`), keeping the closure that increments `$verifications` and runs `$duringVerification`.
- Put `assertUnchanged`, `assertLockout` and `assertError` in `trait AccountEditAssertions` (`source/tests/support/AccountEditAssertions.php`), used by the base.

Move the tests into these classes in `source/tests/integration/Auth/` (each `final`, extending `AccountEditTestCase`, with the original `CoversClass`/`UsesClass` attributes), then delete `AccountEditTest.php`:

| Class | Tests |
|---|---|
| `AccountEditSuccessTest` | testEditsTheUsernameOnly, testEditsTheEmailOnly, testEditsThePasswordOnly, testEditsEveryFieldTogether, testDoesNotRehashTheCurrentDigest, testACaseOnlyUsernameChangeIsAllowed, testIdenticalValuesSkipTheCheckAndSucceed (7) |
| `AccountEditLockoutTest` | testWrongPasswordIsRefusedAndCounted, testLocksAtExactlyTheMaximum, testACounterAboveTheMaximumStillLocks, testLockedAnswers423WithoutCheckingOrCounting, testAnExpiredLockIsClearedBeforeTheAttempt, testAnExpiredLockIsClearedBeforeASuccess, testSuccessResetsTheLockoutRow, testAMissingCallerAnswersInvalidSessionWithoutCounting, testAnAttemptWaitsForTheLockoutRowBeforeCheckingThePassword, testRepeatedWrongPasswordsCheckAtMostTheMaximum (10) |
| `AccountEditTakenTest` | testUsernameTakenByAnotherUserIsRefusedAndCounted, testEmailTakenByAnotherUserIsRefusedAndCounted, testUsernameIsCheckedBeforeEmail, testAUsernameCommittedAfterTheSnapshotIsRefusedAndCounted, testAnEmailCommittedAfterTheSnapshotIsRefusedAndCounted, testAUsernameRaceOnUpdateAppliesNothingAndIsCounted, testAnEmailRaceOnUpdateAppliesNothingAndIsCounted (7) |
| `AccountEditResetTokensTest` | testAnEmailChangeMarksEveryUnusedResetTokenUsed, testAPasswordChangeMarksEveryUnusedResetTokenUsed, testAUsernameOnlyChangeLeavesResetTokensUnused, testAFailureLeavesResetTokensUnused (4) |

Total: 28 tests. Add `UsesClass(CountingPasswordVerifier::class)` only if PHPUnit's strict coverage requires it (support classes are outside the covered source, so it should not).

## Files to Change
- `source/tests/support/AccountEditTestCase.php`, `AccountEditAssertions.php`, `CountingPasswordVerifier.php` — new.
- `source/tests/integration/Auth/AccountEdit{Success,Lockout,Taken,ResetTokens}Test.php` — new.
- `source/tests/integration/Auth/AccountEditTest.php` — deleted.
