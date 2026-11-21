# check-config lease rule

Make `bin/check-config` reject an `SMTP_TOTAL_TIMEOUT` that could outlive the idempotency lease.

- In `Infra/ConfigValidator.php`, extend `checkSmtpTimeouts()` (or add a sibling check): on the effective value (default `25`), a valid integer `>= 300` adds `SMTP_TOTAL_TIMEOUT: not below the idempotency lease (300)`. An invalid value keeps reporting only `not a positive integer`.
- Reference the lease constant from the step-02 service rather than repeating `300`.
- Unit tests: `299` passes, `300` and above fail, the default passes, an invalid value reports only the integer failure.

## Files to Change

- `source/lib/Infra/ConfigValidator.php` — the new rule.
- `source/tests/unit/Infra/ConfigValidator/` — the matching test file.
