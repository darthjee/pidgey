# Verify

1. **Test count**: before the change (on `origin/main`), record the number of tests reported by `vendor/bin/phpunit --list-tests tests/unit/Smtp tests/integration/Smtp` (or a `--testsuite` run filtered to `Smtp`). After the change the count must be equal or higher, and the list of test names (ignoring class names) must be the same, except for the one renamed coverage check.
2. **Lint**: `composer lint` (phpcs + phpmd unused code) passes. phpmd catches any base-class helper left unused.
3. **Suite**: `make tests` (unit + integration, with MySQL and the plain/STARTTLS/SSL Mailpit services up) passes, and `lib/Smtp` coverage in the generated report matches `origin/main`.
4. **Complexity**: run `darthjee/tingle_php_complexity:0.9.0` on `source/tests`. Every new or touched file (the 8 unit classes, 5 integration classes and 2 base classes, plus the trait if one was added) must score CCN < 20. Neither old `SmtpTransportTest.php` may remain.

## Files to Change
- None (verification only).
