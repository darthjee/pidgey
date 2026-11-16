# Add the UserRepository base test case and split UserRepositoryTest

Create `abstract class UserRepositoryTestCase extends RepositoryTestCase` in `source/tests/support/UserRepositoryTestCase.php` holding `SESSION`, `OTHER_SESSION`, `$repository`, `setUp`, and the helpers `now`, `at`, `createUser`.

Move the tests into these classes in `source/tests/integration/Repository/` (each `final`, extending `UserRepositoryTestCase`, with the original `CoversClass`/`UsesClass` attributes), then delete `UserRepositoryTest.php`:

| Class | Tests (and providers) |
|---|---|
| `UserRepositoryWriteTest` | testCreateReturnsEveryField, testCreateStoresIsAdminWhenPassed, statuses + testCreateStoresTheStatusAsPassed, testCreateRejectsAnUnknownOrganization, duplicates + testCreateRejectsDuplicates, testTrailingSpaceIsADistinctValue, testUpdatePasswordDigestStoresTheNewDigest, testUpdateAccountStoresEveryValue, testUpdateAccountRejectsAUsernameTakenByAnotherUser (9) |
| `UserRepositoryFindTest` | testFindByIdFindsAFixtureRow, testFindByIdReturnsNullWhenNotFound, testFindByUsernameFindsAFixtureRow, testFindByUsernameReturnsNullWhenNotFound, testFindByEmailFindsAFixtureRow, testFindByEmailReturnsNullWhenNotFound, testFindInOrganizationFindsAUserOfThatOrganization, testFindInOrganizationReturnsNullWhenNotFound, testFindInOrganizationIgnoresAUserOfAnotherOrganization, testFindByUsernameIgnoresCaseAndAccents, testFindByEmailIgnoresCaseAndAccents, testFindApprovedEmailReturnsTheStoredValueForADifferentCase, testFindApprovedEmailIgnoresPendingDeniedAndOtherOrganizations (13) |
| `UserRepositoryTakenTest` | testFindTakenFindsAUsernameMatch, testFindTakenFindsAnEmailMatch, testFindTakenPrefersTheUsernameRowWhenTwoRowsMatch, testFindTakenPrefersTheUsernameRowWhateverTheInsertOrder, testFindTakenIgnoresCaseAndAccents, testFindTakenCountsADeniedUser, testFindTakenReturnsNullWhenNothingMatches, testUsernameTakenByOtherIgnoresTheCallersOwnRow, testUsernameTakenByOtherMatchesAnotherUserCaseAndAccentInsensitively, testEmailTakenByOtherIgnoresTheCallersOwnRow, testEmailTakenByOtherMatchesAnotherUserCaseInsensitively (11) |
| `UserRepositorySessionTest` | testFindApprovedBySessionFindsTheUserOfALiveSession, testFindApprovedBySessionFindsTheLiveRowOfARotatedSession, testFindApprovedBySessionIgnoresARevokedSession, expiredOffsets + testFindApprovedBySessionIgnoresAnExpiredSession, testFindApprovedBySessionComparesAgainstTheGivenNow, testFindApprovedBySessionIgnoresAnUnknownSession, testFindApprovedBySessionIgnoresTheSessionOfAnotherUser, unapprovedStatuses + testFindApprovedBySessionIgnoresAUserThatIsNotApproved, testFindApprovedBySessionIgnoresADeletedUser (9) |

Total: 42 tests. If `UserRepositoryFindTest` reaches CCN 20, move the two `findApprovedEmail*` tests into `UserRepositoryApprovedEmailTest`.

## Files to Change
- `source/tests/support/UserRepositoryTestCase.php` — new.
- `source/tests/integration/Repository/UserRepository{Write,Find,Taken,Session}Test.php` — new.
- `source/tests/integration/Repository/UserRepositoryTest.php` — deleted.
