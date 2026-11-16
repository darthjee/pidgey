# Issue: Reduce complexity of the Auth, Repository and Schema integration tests

## Description

Reduce the complexity of the Auth, Repository and Schema integration tests, part of Epic #205 (measured with `darthjee/tingle_php_complexity:0.9.0`, thresholds warn=20 / error=40 / critical=60).

| Status | CCN | Lines | Dup | File |
|---|---|---|---|---|
| ERROR | 56 | 135 | 0 | `source/tests/integration/Schema/SchemaTest.php` |
| ERROR | 51 | 227 | 69 | `source/tests/integration/Repository/UserRepositoryTest.php` |
| ERROR | 43 | 257 | 109 | `source/tests/integration/Auth/AccountEditTest.php` |
| ERROR | 42 | 279 | 121 | `source/tests/integration/Auth/SessionsTest.php` |

## Problem

Each method is already simple (max CCN 2–3); the file-level CCN is high because each class holds many tests:

- `AccountEditTest`: 28 tests plus ~10 private helpers and an inline verifier/clock setup.
- `SessionsTest`: 21 tests, 3 data providers and ~12 private helpers (row readers, two anonymous clocks).
- `UserRepositoryTest`: ~43 tests and 4 data providers.
- `SchemaTest`: 55 one-line tests — roughly 5 per table (columns, unique keys, indexes, foreign keys, collation) across 9 tables — plus the `information_schema` helpers.

## Expected Behavior

- Every test file produced or touched scores CCN < 20 (below warn) in `darthjee/tingle_php_complexity:0.9.0`.
- The number of tests in this area is the same or higher (data-provider cases count); the full PHPUnit suite (unit + integration) passes; coverage is unchanged.
- No production code changes; no test case is removed or weakened.
- `CoversClass`/`UsesClass` attributes are repeated on every concrete class.

## Solution

Follow the pattern of #206–#208: an abstract base class (under `source/tests/support/`) holding the shared constants, setUp/tearDown and helpers, extended by focused concrete classes. If a base class itself reaches CCN 20, move its assertions into an area-specific trait. Delete each original file.

**AccountEdit** — `AccountEditTestCase extends SessionEndpointTestCase` (constants, class-level passwords/digest, verifier and clock setup, `service`, `commitConcurrently`, `user`, `reload`, `wrongPassword`, `assertUnchanged`, `assertLockout`, `assertError`):
- `AccountEditSuccessTest` — field edits, no rehash, case-only username, identical values (7).
- `AccountEditLockoutTest` — wrong password counting, lock at/above max, 423 when locked, expired lock, reset on success, missing caller, lockout-row wait, bounded checks (10).
- `AccountEditTakenTest` — username/email taken, check order, snapshot and race cases (7).
- `AccountEditResetTokensTest` — reset-token invalidation on email/password change and non-invalidation otherwise (4).

**Sessions** — `SessionsTestCase extends IntegrationTestCase` (constants, setUp/tearDown, fixtures, row readers, `user`, `token`, `entity`, `deleteUser`, `at`, clocks):
- `SessionsStartTest` (5), `SessionsRefreshTest` (9 tests + 2 providers), `SessionsLogoutTest` (logout, stale tokens, unknown token, revoke-all), `SessionsFindActiveTest` (find-active cases + revoke).

**UserRepository** — `UserRepositoryTestCase extends RepositoryTestCase` (session constants, repository setUp, `now`, `at`, `createUser`):
- `UserRepositoryWriteTest` — create (fields, admin, statuses, unknown organization, duplicates, trailing space) and updates (password digest, account values, taken username).
- `UserRepositoryFindTest` — find by id/username/email/organization, case/accent insensitivity, `findApprovedEmailInOrganization`.
- `UserRepositoryTakenTest` — `findTaken`, `usernameTakenByOther`, `emailTakenByOther`.
- `UserRepositorySessionTest` — `findApprovedBySession` cases and their providers.

**Schema** — split by aspect, fed by data providers with one row per table. `SchemaTestCase extends IntegrationTestCase` holds the shared column/key constants (`ID`, `VARCHAR_255`, `ORGANIZATION_FK`, …) and the `information_schema` helpers (`columns`, `uniqueKeys`, `indexes`, `foreignKeys`, `collation`):
- `SchemaTablesTest` — table set, plus collation for every table via a provider.
- `SchemaColumnsTest` — columns for every table via a provider, plus the `users.status` no-default and the generated `smtp_connections.active_organization_id` tests.
- `SchemaKeysTest` — unique keys, plain indexes and foreign keys for every table, each via a provider.

Responsible agent: `backend`.

## Benefits

- Removes 4 ERROR entries from the complexity report.
- Focused classes make failures easier to locate; the schema expectations become declarative tables per aspect.
- Shared arrange code lives in one place per area, cutting duplication.
