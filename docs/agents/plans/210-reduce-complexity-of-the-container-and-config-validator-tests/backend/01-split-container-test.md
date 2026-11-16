# Add the Container base test case and split ContainerTest

Create abstract `Pidgey\Tests\Support\ContainerTestCase extends TestCase` holding `VARIABLES`, `SECRET_KEY`, `SMTP_KEY`, `PREVIOUS_SMTP_KEY` (protected const), `$saved`, `setUp`/`tearDown` unchanged, and the helpers `unusedConnections` and `handlerLevel` (protected). Drop `fixedClock`; tests that used it call `new FrozenWallClock($timestamp)` instead (same timestamp values).

Move the tests into `source/tests/unit/Container/` (namespace `Pidgey\Tests\Unit\Container`):

- `ContainerDatabaseTest`: `testConstructorConnectsNothing`, `testConstructorNeedsNoDatabaseVariables`, `testPdoConnectsOnceWithTheEnvironmentConfig`, `testPdoReportsAMissingVariableByName`, `testTransactionIsMemoizedAndWrapsTheSamePdo`, plus `testRepositoryIsMemoizedOnASinglePdo(string $accessor, string $class, array $siblings)` driven by provider `repositories`. One row per former test, keyed by the old name, with the same accessor, class and sibling calls:
  - organization: `organizationRepository`, `OrganizationRepository`, `[userRepository, transaction]`
  - user: `userRepository`, `UserRepository`, `[organizationRepository, pdo]`
  - refreshToken / passwordResetToken / authorizationRequest / accountEditLockout: their accessor and class, `[userRepository, pdo]`
  - apiToken / smtpConnection: their accessor and class, `[organizationRepository, pdo]`
  The body keeps the original arrange (`ConnectionFactory` mock expecting `create` once, returning a PDO stub), asserts `assertInstanceOf($class, ...)` and `assertSame` on a second call, then calls each sibling.
- `ContainerInfraTest`: wall clock (2), clock (2), logger (`IsMemoized`, `CanBeInjected`, `DefaultsToThePidgeyChannel`, `LevelFollowsTheEnvironment`), operator log (1).
- `ContainerAuthTest`: access token (4), sessions / session rotation (3), passwords, registration, login, account edit (3), user approval (2).
- `ContainerSmtpTest`: SMTP transport, host policy (5), MIME builder, send flow.
- `ContainerSmtpSettingsTest`: password cipher (3), SMTP session settings (5).

Totals: 46 test methods, 53 test cases (unchanged). Delete `source/tests/unit/ContainerTest.php`.

## Files to Change
- `source/tests/support/ContainerTestCase.php` — new abstract base.
- `source/tests/unit/Container/ContainerDatabaseTest.php` — new.
- `source/tests/unit/Container/ContainerInfraTest.php` — new.
- `source/tests/unit/Container/ContainerAuthTest.php` — new.
- `source/tests/unit/Container/ContainerSmtpTest.php` — new.
- `source/tests/unit/Container/ContainerSmtpSettingsTest.php` — new.
- `source/tests/unit/ContainerTest.php` — deleted.
