# Plan: Reduce complexity of the Auth, Repository and Schema integration tests

Issue: [209-reduce-complexity-of-the-auth-repository-and-schema-integration-tests.md](../../issues/209-reduce-complexity-of-the-auth-repository-and-schema-integration-tests.md)

## Overview
Split `source/tests/integration/Schema/SchemaTest.php` (CCN 56), `source/tests/integration/Repository/UserRepositoryTest.php` (CCN 51), `source/tests/integration/Auth/AccountEditTest.php` (CCN 43) and `source/tests/integration/Auth/SessionsTest.php` (CCN 42) into focused classes on top of new abstract base test cases under `source/tests/support/`, so every new or touched file scores CCN < 20. Test code only. Auth and Repository tests move verbatim (28 + 22 + 42 test methods); the 46 schema tests become data-provider-driven tests split by aspect (48 test cases, no expectation dropped).

See [backend.md](backend.md) for the full plan.
