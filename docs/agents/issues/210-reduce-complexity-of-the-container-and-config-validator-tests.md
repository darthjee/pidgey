# Issue: Reduce complexity of the container and config validator tests

## Description

Reduce the complexity of the container and config validator unit tests. Both are flagged CRITICAL by `darthjee/tingle_php_complexity:0.9.0` (thresholds warn=20 / error=40 / critical=60). This is a sub-issue of the epic #205.

| Status | CCN | Lines | Dup | File | Tests |
|---|---|---|---|---|---|
| CRITICAL | 90 | 1011 | 152 | `source/tests/unit/Infra/ConfigValidatorTest.php` | 43 |
| CRITICAL | 61 | 775 | 127 | `source/tests/unit/ContainerTest.php` | 53 |

## Problem

Each method already has low complexity (max CCN 2–5). The file-level CCN is high for two reasons. Each class holds many tests and data providers. Arrange code is also duplicated:

- Both classes save, clear and restore a long list of environment variables in `setUp`/`tearDown`.
- `ContainerTest` has 8 near-identical "`<X>Repository` is memoized on a single PDO" tests. It also defines an inline anonymous `WallClock` (`fixedClock`) that duplicates the existing `FrozenWallClock` support class.
- `ConfigValidatorTest` repeats the same development/production key checks for the secret key and for the SMTP encryption key.

## Expected Behavior

- Every test file produced or touched scores CCN < 20 (below warn).
- No test case is removed or weakened. Test names and assertions are unchanged except where a group of identical tests becomes provider rows, and the number of test cases stays the same or grows.
- The full PHPUnit suite (unit + integration) passes and coverage is unchanged.
- Only test code changes; nothing under `source/lib` is touched.

## Solution

Responsible agent: `backend`. Follow the pattern used by #206–#209: an abstract `*TestCase` base class under `source/tests/support/`, with CoversClass/UsesClass attributes repeated on every concrete class.

### ContainerTest → by service group + providers

- New abstract `ContainerTestCase extends TestCase` holds:
  - the environment variable list, the keys and the save/restore `setUp`/`tearDown`;
  - the helpers `unusedConnections` and `handlerLevel`.
- Replace `fixedClock` with `FrozenWallClock`.
- Concrete classes, grouped by the services they resolve:
  - `ContainerDatabaseTest`: constructor, `pdo`, `transaction` and the repositories. The 8 repository-memoization tests become one provider-driven test. Each row is (accessor, expected class, sibling calls), so there are still 8 cases.
  - `ContainerAuthTest`: access token, sessions, session rotation, passwords, registration, login, account edit, user approval.
  - `ContainerSmtpTest`: SMTP transport, host policy, MIME builder, send flow, password cipher, SMTP session settings.
  - `ContainerInfraTest`: clocks, logger, operator log.
- Split a group further if it still reaches CCN 20.

### ConfigValidatorTest → by rule area

- New abstract `ConfigValidatorTestCase extends TestCase` holds:
  - the key constants, the variable list and the baseline environment in `setUp`/`tearDown`;
  - the `assertNoValueLeaks` and `assertNoOriginLeaks` helpers;
  - the shared `productionEnvironments`/`developmentEnvironments` providers.
- Concrete classes:
  - `ConfigValidatorGeneralTest`: baseline, fully configured, empty defaults, single rule failures, migration user pairing, DB port, several failures.
  - `ConfigValidatorSecretKeyTest`: previous keys and the development secret key in production or development.
  - `ConfigValidatorSmtpKeyTest`: SMTP encryption keys, the development SMTP key, sodium, and `validateSmtpEncryption`.
  - `ConfigValidatorLogAndOriginsTest`: log level and allowed origins.
  - `ConfigValidatorSmtpSettingsTest`: SMTP timeouts, CA file, EHLO name, test settings.
- Split a class further if it still reaches CCN 20.

## Benefits

- Removes the last two CRITICAL entries from the test-suite complexity report, which completes the #205 epic.
- Smaller classes focused on one concern are easier to navigate and extend when a new service or config rule is added.
- Shared environment handling in one base class per area removes the duplicated save/restore code.
