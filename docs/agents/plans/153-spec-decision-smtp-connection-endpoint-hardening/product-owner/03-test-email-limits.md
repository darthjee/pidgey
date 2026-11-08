# Test-email rate limit and recipient restriction

Decision 3 in the test-email section of `api/endpoints/pidgey-admin/smtp-connections.md`.

- **Recipient:** `to` must be the email of a user of the caller's organization (default the
  caller's own); otherwise `400 VALIDATION_FAILED` —
  `to must be the email of a user of the organization`.
- **Order of checks:** validation (`to`), connection lookup (`404`), then the limit, then the
  send-time checks of the send flow.
- **Window:** per organization, fixed window of `SMTP_TEST_WINDOW` seconds (default `3600`)
  allowing `SMTP_TEST_MAX_ATTEMPTS` attempts (default `10`); every attempt that passed
  validation and lookup counts, whatever its outcome. Exceeded → `429 TOO_MANY_REQUESTS` —
  `Too many test emails; try again later`, `Retry-After` = seconds until the window ends.
- **Lease:** at most one test in flight per organization: in a short transaction locking the
  organization's `smtp_test_limits` row, refuse if `busy_until > now`
  (`429 TOO_MANY_REQUESTS` — `A test email is already in progress`, `Retry-After` = seconds
  until `busy_until`), else count the attempt and set
  `busy_until = now + SMTP_TOTAL_TIMEOUT + 5`; commit, send, then clear `busy_until`. No lock
  held during the send; a crashed worker's lease expires on its own.
- Add the cases to the failure and errors tables, edge cases and testable rules; replace the
  "No rate limit" known limitation; in `api/pending.md` mark the test email part of the
  rate-limit item as decided (link here), keeping the rest open.
- `api/conventions/errors.md`: `429 TOO_MANY_REQUESTS` is now emitted (test email only),
  with `Retry-After`.

## Files to Change
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`
- `docs/agents/specs/api/conventions/errors.md`
- `docs/agents/specs/api/pending.md`
