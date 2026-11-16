# Verify complexity, tests and lint
1. Run `darthjee/tingle_php_complexity:0.9.0` against `source/tests` (same invocation as the report in #205). Every file created in steps 01–02 must be CCN < 20; if one is not, split it further along concern lines (never drop or merge tests). After this issue, the report should list no ERROR or CRITICAL files.
2. Run `make tests` (docker: `composer lint && composer tests`, needs MySQL and Mailpit). All green, no new risky/warning results.
3. Confirm the test count: compare PHPUnit's count for `--filter 'Container|ConfigValidator'` before and after; it must be identical (53 + 43 cases).

## Files to Change
- None (verification only; fix files from steps 01–02 if needed).
