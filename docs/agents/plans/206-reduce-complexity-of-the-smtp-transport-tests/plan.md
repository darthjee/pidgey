# Plan: Reduce complexity of the SMTP transport tests

Issue: [206-reduce-complexity-of-the-smtp-transport-tests.md](../../issues/206-reduce-complexity-of-the-smtp-transport-tests.md)

## Overview
Split the unit and integration `SmtpTransportTest` classes (CCN 94 and 47) into focused classes that each score CCN < 20. Shared setup, constants and SMTP helpers move into two new abstract base TestCases under `source/tests/support/`. The failure-code self-check moves into its own class that maps codes to `[Class::class, 'test']`. Test code only.

See [backend.md](backend.md) for the full plan.
