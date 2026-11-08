# SMTP connection endpoint rules

Apply Decisions 2, 6 and 12 and the backward-compatibility rules to the admin endpoints.

- `host` rule: ASCII LDH hostname (labels 1–63, no leading / trailing hyphen, total ≤ 253)
  or IP literal; it is the connect target and TLS peer name, not the `EHLO` argument.
- New validation on create / update (merged state): credentials with `encryption: none` →
  `username and password require encryption tls or ssl`.
- Test email: the new `409 SMTP_INSECURE_CREDENTIALS` and `502 SMTP_PROTOCOL_ERROR` in the
  error table.
- Known limitations: credential redirection no longer covers cleartext (`none` is refused);
  slow servers bounded by `SMTP_TOTAL_TIMEOUT`.
- Backward compatibility: no migration; violating rows behave as in the issue.
- Edge cases list: add credentials + `none`, Unicode host.

## Files to Change
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md` — field rules,
  validation, errors, known limitations, edge cases.
