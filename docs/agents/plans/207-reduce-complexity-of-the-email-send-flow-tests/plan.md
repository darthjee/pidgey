# Plan: Reduce complexity of the email send flow tests

Issue: [207-reduce-complexity-of-the-email-send-flow-tests.md](../../issues/207-reduce-complexity-of-the-email-send-flow-tests.md)

## Overview
Split `source/tests/unit/Email/SendFlowTest.php` (CCN 40) and `source/tests/integration/Email/SendFlowTest.php` (CCN 51) into focused classes on top of two new abstract base test cases under `source/tests/support/`, so every new or touched file scores CCN < 20. Test code only; every test and provider row is moved verbatim.

See [backend.md](backend.md) for the full plan.
