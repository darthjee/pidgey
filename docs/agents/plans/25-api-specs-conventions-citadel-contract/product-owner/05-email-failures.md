# Align email failure codes and send flow
Update the email specs to the unified error contract:

- `email/failures.md`: rename codes to upper case (`VALIDATION_FAILED`, `NO_SMTP_CONNECTION`,
  `SMTP_CONNECTION_FAILED`, `SMTP_TLS_FAILED`, `SMTP_AUTH_FAILED`, `SMTP_REJECTED`,
  `SMTP_TEMPORARY_FAILURE`, `SMTP_TIMEOUT`, `INTERNAL_ERROR`); validation is `400`; keep
  409/502/503/504/500 for the others; the SMTP reply goes in `error.smtp` (not `details`);
  link to `api/conventions/errors.md`. Keep the "exact HTTP status mapping" pending decision.
- `email/send-flow.md`: invalid payload → `400 VALIDATION_FAILED`; no active connection →
  `NO_SMTP_CONNECTION`.

## Files to Change
- `docs/agents/specs/email/failures.md` — codes, statuses, `error.smtp`.
- `docs/agents/specs/email/send-flow.md` — status and code names.
