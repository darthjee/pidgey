# Plan: Reduce complexity of the HTTP tests

Issue: [208-reduce-complexity-of-the-http-tests.md](../../issues/208-reduce-complexity-of-the-http-tests.md)

## Overview
Split `source/tests/unit/Http/RequestTest.php` (CCN 67), `source/tests/unit/Http/SmtpConnectionRulesTest.php` (CCN 48) and `source/tests/integration/Http/Handler/SmtpConnectionTestTest.php` (CCN 40) into focused classes on top of three new abstract base test cases under `source/tests/support/`, so every new or touched file scores CCN < 20. Test code only; every test and provider row is moved verbatim (52 + 30 + 18 = 100 test methods).

See [backend.md](backend.md) for the full plan.
