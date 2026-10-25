# Recovery endpoints

**`endpoints/auth/recovery.md`**:

- `POST /auth/recover.json`: `email` (valid email, trimmed). Always `200 { "sent": true }`.
  User `approved`/`pending`: unless the **cooldown** applies (a token created less than
  `PASSWORD_RESET_COOLDOWN` seconds ago → no token, no email), create a token and send the
  recovery email **synchronously**; every failure is logged and swallowed (never `SMTP_*`
  or `error.smtp`). Unknown or `denied`: nothing. Same status, body and log line in every
  case. **Known limitation**: response time reveals whether the email belongs to an
  approved/pending user (SMTP round trip), bounded by the SMTP timeouts.
- `POST /auth/reset-password.json`: `token` (not empty), `password` (min 8, max 72 bytes);
  `password_confirmation` ignored. Not found/used/expired, or user missing/`denied` →
  `400 BAD_REQUEST Invalid or expired token` (user check before hashing; token not
  consumed). Valid: in one transaction, consume the token conditionally
  (`UPDATE … SET used_at = ? WHERE id = ? AND used_at IS NULL AND expires_at > ?`; 0 rows →
  the same `400`, nothing changed), update `password_digest`, revoke every active refresh
  token with `password_reset`; `200 { "reset": true }`. Never `401`. Pending stays pending.
  Does not clear an account-edit lockout.
- **Password-reset tokens**: 48 CSPRNG bytes hex (96 chars), stored as SHA-256 hex in
  `password_reset_tokens`; lifetime `PASSWORD_RESET_TOKEN_TTL`; single use; issuing one
  does not invalidate others; link `{FRONTEND_BASE_URL}/#/recover-password?token={token}`.
- **System email** (decision 2): sent through the **active SMTP connection of the system
  organization** with the send flow's SMTP client (timeouts, TLS, failure categories),
  `From` = the connection's `from_address`. Subject `Reset your Pidgey password`, plain text
  (greeting, link, single-use/expiry/ignore note). **Not sent** (logged no-op reporting
  "not sent") when there is no system organization, it has no active connection, or
  `FRONTEND_BASE_URL` is empty. Security note: members of the system organization can
  redirect password-reset mail of the whole instance; trusted like the admin.
- Pending decisions: how the system organization is marked (setup flow; e.g. an
  `is_system` flag, at most one); outbox table + worker for asynchronous system email
  (also #30's send-recovery-email); whether #30's admin send bypasses the cooldown (leaning
  yes, decided in #30).

## Files to Change
- `docs/agents/specs/api/endpoints/auth/recovery.md` — new.
