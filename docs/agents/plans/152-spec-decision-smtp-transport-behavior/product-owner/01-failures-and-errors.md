# Failure codes and error messages

Write Decision 8 (and the codes introduced by Decisions 2 and 4) into the failure taxonomy.

- `email/failures.md`: replace the code table with the full table of Decision 8 (HTTP
  status, fixed `error.message`, when `error.smtp` is present), adding
  `SMTP_INSECURE_CREDENTIALS` (409) and `SMTP_PROTOCOL_ERROR` (502); the `SMTP_AUTH_FAILED`
  variants (rejected credentials / no supported mechanism / `530` at `MAIL FROM` →
  `The SMTP server requires authentication`); the step-specific `SMTP_REJECTED` messages;
  the Decision 3 hints appended to the `SMTP_TIMEOUT` / `SMTP_TLS_FAILED` messages; the
  "step wins over reply class" rule (`STARTTLS`, `AUTH`); the timeout → code rule
  (Decision 5); sanitization (printable ASCII, `?` replacement, whitespace collapse, cap
  512 with `...`) and echo redaction (Decision 9). Remove the three Pending decisions.
- `api/conventions/errors.md` (`SMTP send failures`, `Specific codes`): every
  `error.message` is fixed; add the two new codes; fix the example to
  `"message": "The SMTP server rejected the recipient"`.

## Files to Change
- `docs/agents/specs/email/failures.md` — full code table, rules, pending items removed.
- `docs/agents/specs/api/conventions/errors.md` — fixed messages, new codes, example.
