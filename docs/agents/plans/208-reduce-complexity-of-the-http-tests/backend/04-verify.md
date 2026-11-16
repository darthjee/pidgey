# Verify complexity, tests and lint
1. Run `darthjee/tingle_php_complexity:0.9.0` against `source/tests` (same invocation as the report in #205). Every file created in steps 01–03 must be CCN < 20; if one is not, move helpers into a trait or split the class further (never drop or merge tests).
2. Run `make tests` (docker: `composer lint && composer tests`, needs MySQL and Mailpit). All green, no new risky/warning results.
3. Confirm the test count is unchanged: 52 Request + 30 SmtpConnectionRules + 18 SmtpConnectionTest handler test methods, every provider row present (compare PHPUnit's count for `--filter 'Request|SmtpConnectionRules|SmtpConnectionTest'` before and after).

## Files to Change
- None (verification only; fix files from steps 01–03 if needed).
