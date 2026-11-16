# Plan: Reduce complexity of the container and config validator tests

Issue: [210-reduce-complexity-of-the-container-and-config-validator-tests.md](../../issues/210-reduce-complexity-of-the-container-and-config-validator-tests.md)

## Overview
Split `source/tests/unit/Infra/ConfigValidatorTest.php` (CCN 90, 43 tests) and `source/tests/unit/ContainerTest.php` (CCN 61, 53 tests) into focused classes on top of two new abstract base test cases under `source/tests/support/`, so every new or touched file scores CCN < 20. Test code only. ConfigValidator tests move verbatim. In ContainerTest, the 8 repository-memoization tests are folded into one data-provider-driven test (8 rows, same assertions); every other test moves verbatim. Test case count stays 43 + 53.

See [backend.md](backend.md) for the full plan.
