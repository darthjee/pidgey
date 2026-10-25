# Account endpoint and lockout

**`endpoints/auth/account.md`** — `PATCH /auth/account.json` (gated; a `pending` user gets
the gate's `401`):

- Fields: `currentPassword` (required, not empty), optional `username` (not empty, max
  255), `email` (valid, max 255), `newPassword` (min 8, max 72 bytes); trimming per
  `auth.md`.
- Steps in order (Citadel 4.8): none of the three present → `400 BAD_REQUEST At least one
  of username, email, or newPassword is required` (not a failed attempt); locked →
  `423 LOCKED`; caller row missing → `401 Invalid session`; failed-attempt checks: wrong
  `currentPassword` → `400 Invalid current password`, taken `username` / `email` →
  `409 USERNAME_TAKEN` / `EMAIL_TAKEN` (`Username already in use` / `Email already in use`);
  apply changes and reset the counter; `200 { "username", "email" }`.
- "Taken" checks only when the value differs **byte-wise** from the current one, and exclude
  the caller's row (`id <> caller`): a case-only change is allowed.
- No rehash on account edit; a password change does **not** revoke sessions; a new email is
  not verified.
- **Lockout** (`account_edit_lockouts`): atomic upsert
  `INSERT … ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1` (covers the
  first-insert race); at `ACCOUNT_EDIT_MAX_ATTEMPTS`, `locked_until = now +
  ACCOUNT_EDIT_LOCK_TTL`; an **expired** lock is cleared (counter 0) before the attempt is
  evaluated; success resets to `0` / `null`. `now` bound from PHP (link
  `authentication/sessions.md#clock`).

## Files to Change
- `docs/agents/specs/api/endpoints/auth/account.md` — new.
