# Add the Sessions base test case and split SessionsTest

Create `abstract class SessionsTestCase extends IntegrationTestCase` in `source/tests/support/SessionsTestCase.php` holding the constants, state, `setUp`/`tearDown` (truncating as today) and the helpers `entity`, `deleteUser`, `user`, `token`, `rows`, `rowByHash`, `onlyRow`, `rowCount`, `at`.

To keep the base below CCN 20:
- Replace `clockAt($t)` with `new FrozenWallClock($t)` (same fixed UTC instant).
- Move the `tickingClockFrom` anonymous class into a named `final class TickingWallClock implements WallClock` in `source/tests/support/` (starts at a timestamp, returns it in UTC and advances 60 s on every `now()`), used by `testRefreshReadsTheClockOnceForTheWholeRotation`.
- If the base still reaches CCN 20, move the row readers (`rows`, `rowByHash`, `onlyRow`, `rowCount`) into `trait SessionsRowReaders`.

Move the tests into these classes in `source/tests/integration/Auth/` (each `final`, extending `SessionsTestCase`, with the original `CoversClass`/`UsesClass` attributes), then delete `SessionsTest.php`:

| Class | Tests (and providers) |
|---|---|
| `SessionsStartTest` | testStartStoresTheHashNeverThePlainToken, testStartStoresTheSessionTimesAndUser, testStartIssuesAnAccessTokenForTheSession, testEachStartOpensADistinctSession, testStartRunsInsideTheCallersTransaction (5) |
| `SessionsRefreshTest` | liveStatuses + testRefreshRotatesTheTokenOfALiveUser, testRefreshReadsTheClockOnceForTheWholeRotation, testRefreshWithAnUnknownTokenFails, testRefreshWithAnExpiredTokenFails, nonRotatedReasons + testRefreshWithATokenRevokedForAnotherReasonFailsWithoutSideEffect, testReplayingARotatedTokenRevokesEveryActiveTokenOfTheUser, testRefreshOfADeniedUserRevokesOnlyThePresentedToken, testRefreshFailsWhenTheUserRowIsMissing, testARotatedChainDetectsTheReplayOfAnOldToken (9) |
| `SessionsLogoutTest` | testLogoutRevokesOnlyThePresentedSession, staleTokens + testLogoutWithAStaleTokenStillRevokesItsSession, testLogoutWithAnUnknownTokenChangesNothing, testRevokeAllForUserRevokesOnlyActiveRowsOfThatUser (4) |
| `SessionsFindActiveTest` | testFindActiveReturnsTheTokenAndItsUserWithoutChangingAnything, testFindActiveIgnoresUnknownRevokedAndExpiredTokens, testFindActiveIgnoresATokenWhoseUserIsMissing, testRevokeRevokesAnActiveTokenAndLeavesARevokedOneAlone (4) |

Total: 22 tests.

## Files to Change
- `source/tests/support/SessionsTestCase.php`, `TickingWallClock.php` (and `SessionsRowReaders.php` if needed) — new.
- `source/tests/integration/Auth/Sessions{Start,Refresh,Logout,FindActive}Test.php` — new.
- `source/tests/integration/Auth/SessionsTest.php` — deleted.
