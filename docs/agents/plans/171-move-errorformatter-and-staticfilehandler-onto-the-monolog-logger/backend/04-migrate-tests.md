# Migrate the remaining tests

Every other test that passes a `string` callable as the `ErrorFormatter` logger moves to a
`LoggerInterface`, preferably `Pidgey\Tests\Support\CapturedLog`. Where a test asserted the
logged line, assert the record's level (`ERROR`) and message instead; where it only silenced
the log, pass `(new CapturedLog())->logger()` (or `new NullLogger()`).

- `FrontControllerTest`: the formatter in `setUp()`; the 500 test asserts one `ERROR` record.
  `testDefaultsToTheStandardErrorFormatter` keeps working with the new default.
- `ProcessingRequestTest`, `AdminCheckTest`, `GateTest`.
- `SessionEndpointTestCase`, `SmtpConnectionEndpointTestCase` (support base classes — check
  every subclass still passes).
- Check the exception tests (`ConflictExceptionTest`, `LockedExceptionTest`,
  `ForbiddenExceptionTest`, `UnauthorizedExceptionTest`): they construct `ErrorFormatter`
  without a logger or with a clock only; adjust only if they pass a callable.
- Run `make tests` (lint + full suite).

## Files to Change

- `source/tests/unit/Http/FrontControllerTest.php`
- `source/tests/unit/Http/ProcessingRequestTest.php`
- `source/tests/unit/Auth/AdminCheckTest.php`
- `source/tests/integration/Auth/GateTest.php`
- `source/tests/support/SessionEndpointTestCase.php`
- `source/tests/support/SmtpConnectionEndpointTestCase.php`
- `source/tests/unit/Http/{Conflict,Locked,Forbidden,Unauthorized}ExceptionTest.php` — only if
  they pass a callable logger.
