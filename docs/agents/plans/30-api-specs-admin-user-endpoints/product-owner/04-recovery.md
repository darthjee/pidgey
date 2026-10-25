# Recovery endpoints

Create `api/endpoints/admin-users/recovery.md` with:

- **Shared rules:**
  - tokens use the mechanism in `auth/recovery.md#password-reset-tokens`;
  - a token can be issued for any status (redemption fails for `denied`);
  - other outstanding tokens are not invalidated;
  - both endpoints **bypass `PASSWORD_RESET_COOLDOWN`**.
- `POST /admin/users/{id}/recovery-link.json`:
  - empty body;
  - answers `201 { resetUrl }`;
  - an empty `FRONTEND_BASE_URL` → `409 CONFLICT` `Recovery links are not configured`, and
    no token is created.
- `POST /admin/users/{id}/send-recovery-email.json`:
  - empty body;
  - when sending is impossible by configuration (no system organization, no active
    connection, empty `FRONTEND_BASE_URL`), no token is created and it answers
    `201 { sent: false }`;
  - otherwise, create the token and send the recovery email synchronously (the sender,
    recipient and content rules of `auth/recovery.md#recovery-email`, to the stored
    `users.email`);
  - answers `{ sent: true }`, or `{ sent: false }` on any SMTP failure, with the token kept;
  - never an `SMTP_*` code, `error.smtp` or `500` because of mail;
  - failures are logged by user id only.
- Edge cases and testable rules: the `409` without a base URL; the cooldown bypass;
  `sent:false` with no token when unconfigured; `sent:false` with the token kept on failure;
  other tokens left valid; a denied user's link refused at redemption.

## Files to Change
- `docs/agents/specs/api/endpoints/admin-users/recovery.md` — new.
