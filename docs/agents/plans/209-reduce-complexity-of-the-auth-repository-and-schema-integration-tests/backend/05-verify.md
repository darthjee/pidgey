# Verify complexity, tests and lint
1. Run `darthjee/tingle_php_complexity:0.9.0` against `source/tests` (same invocation as the report in #205). Every file created in steps 01–04 must be CCN < 20; if one is not, move helpers into a trait or split the class further (never drop or merge tests).
2. Run `make tests` (docker: `composer lint && composer tests`, needs MySQL and Mailpit). All green, no new risky/warning results.
3. Confirm the test count: 28 AccountEdit + 22 Sessions + 42 UserRepository test methods unchanged with every provider row present, and 48 schema test cases (was 46). Compare PHPUnit's count for `--filter 'AccountEdit|Sessions|UserRepository|Schema'` before and after (other classes such as `AccountEditLockoutRepositoryTest` match the filter on both sides, so the difference must be exactly +2).

## Files to Change
- None (verification only; fix files from steps 01–04 if needed).
