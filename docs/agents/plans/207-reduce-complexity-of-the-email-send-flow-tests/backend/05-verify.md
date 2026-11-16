# Verify complexity, tests and lint
1. Run `darthjee/tingle_php_complexity:0.9.0` against `source/tests` (same invocation as the report in #205). Every file created in steps 01–04 must be CCN < 20; if a file is not, move helpers into a trait or split the class further (never drop or merge tests).
2. Run `make tests` (docker: `composer lint && composer tests`, needs MySQL and Mailpit). All green, no new risky/warning results.
3. Confirm the SendFlow test count is unchanged: 26 unit + 16 integration test methods, every provider row present (compare PHPUnit's test count for `--filter SendFlow` before and after).

## Files to Change
- None (verification only; fix files from steps 01–04 if needed).
