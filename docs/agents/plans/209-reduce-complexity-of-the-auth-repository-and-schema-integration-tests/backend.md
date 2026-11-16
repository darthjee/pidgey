# Backend Plan: Reduce complexity of the Auth, Repository and Schema integration tests

Main plan: [plan.md](plan.md)

## Overview
Same pattern as #206 (PR #211), #207 (PR #212) and #208 (PR #213, see `source/tests/support/RequestTestCase.php`, `SmtpConnectionTestEndpointTestCase.php`): move shared constants, `setUp`/`tearDown` and helpers into an abstract base class per subject (plus a trait or small named support class when a base would itself reach CCN ≥ 20), then move each test into a class focused on one concern. Delete each original file once empty. SchemaTest is the exception: by the user's choice, its per-table tests are folded into data providers, one row per table, split by aspect.

## Context
- `AccountEditTest` (`Pidgey\Tests\Integration\Auth`, extends `SessionEndpointTestCase`): 28 tests; constants `MAX_ATTEMPTS`, `LOCK_TTL`, `NOW`, `LOCK_END`; static `$password`, `$wrongPassword`, `$digest` set in `setUpBeforeClass`; state `$verifications`, `$duringVerification`, `$verifier`, `$clock`; `setUp` builds an anonymous counting `PasswordVerifier` and an anonymous fixed `WallClock`; helpers `service`, `commitConcurrently`, `user`, `reload`, `wrongPassword`, `assertUnchanged`, `assertLockout`, `assertError`.
- `SessionsTest` (`Pidgey\Tests\Integration\Auth`, extends `IntegrationTestCase`, no outer transaction, truncates in setUp/tearDown): 22 tests, providers `liveStatuses`, `nonRotatedReasons`, `staleTokens`; constants `KEY`, `NOW`, `TTL`, `ISSUED`, `STARTED`; state `$pdo`, `$fixtures`, `$accessToken`, `$sessions`; helpers `entity`, `deleteUser`, `user`, `token`, `rows`, `rowByHash`, `onlyRow`, `rowCount`, static `at`, `tickingClockFrom` (anonymous clock advancing 60 s per read), `clockAt` (anonymous fixed clock).
- `UserRepositoryTest` (`Pidgey\Tests\Integration\Repository`, extends `RepositoryTestCase`): 42 tests, providers `statuses`, `duplicates`, `expiredOffsets`, `unapprovedStatuses`; constants `SESSION`, `OTHER_SESSION`; `$repository` built in setUp; helpers `now`, `at`, `createUser`.
- `SchemaTest` (`Pidgey\Tests\Integration\Schema`, extends `IntegrationTestCase`, `CoversNothing`): 46 tests — `testTableSet`, per table (organizations, users, refresh_tokens, password_reset_tokens, authorization_requests, account_edit_lockouts, api_tokens, smtp_connections, smtp_test_limits) a columns / unique keys / indexes / foreign keys / collation test (organizations has one `testOrganizationsKeys` asserting `[]` for all three key kinds), plus `testUsersStatusHasNoDefault` and `testSmtpConnectionsActiveOrganizationIdIsGeneratedFromActive`. Every per-table test is a single `assertSame(<expected>, $this-><helper>('<table>'))`. Constants `TABLES`, `COLLATION`, `ID`, `CREATED_AT`, `UPDATED_AT`, `VARCHAR_255`, `CHAR_64`, `BIGINT`, `NULLABLE_BIGINT`, `DATETIME`, `NULLABLE_DATETIME`, `COUNTER`, `ORGANIZATION_FK`, `USER_FK`; helpers `columns`, `uniqueKeys`, `indexes`, `foreignKeys`, `collation`, `indexColumns`.
- `source/tests/support/FrozenWallClock` already exists (`new FrozenWallClock(int|DateTimeImmutable)`, UTC, fixed until `advance()`).
- Support classes autoload as `Pidgey\Tests\Support\` from `source/tests/support/` (PSR-4, autoload-dev).

## Steps

- [01 — Add the AccountEdit base test case and split AccountEditTest](backend/01-split-account-edit-test.md)
- [02 — Add the Sessions base test case and split SessionsTest](backend/02-split-sessions-test.md)
- [03 — Add the UserRepository base test case and split UserRepositoryTest](backend/03-split-user-repository-test.md)
- [04 — Add the Schema base test case and rewrite SchemaTest by aspect](backend/04-split-schema-test.md)
- [05 — Verify complexity, tests and lint](backend/05-verify.md)

## CI Checks
- `source`: `make tests` (runs `composer lint && composer tests` in docker; integration needs MySQL and Mailpit).
- Complexity: `darthjee/tingle_php_complexity:0.9.0` against `source/tests`.

## Notes
- Moving tests must not change assertions, provider rows or test method names (SchemaTest excepted: its expectations move into provider rows unchanged).
- Each new concrete class repeats the full `CoversClass`/`UsesClass`/`CoversNothing` attribute set of its original (PHPUnit does not inherit attributes from the abstract base).
- Private constants/helpers referenced from subclasses become `protected` (static ones `protected static`). Providers are `public static` and may read inherited `protected const` values via `self::`.
- Providers stay in the test class that uses them.
