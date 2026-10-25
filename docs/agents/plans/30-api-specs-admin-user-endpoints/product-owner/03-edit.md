# Edit endpoint

Create `api/endpoints/admin-users/edit.md` with `POST /admin/users/{id}/edit.json`:

- **Fields:**
  - `username`: not empty after trimming, max 255 characters;
  - `email`: valid email after trimming, max 255 characters;
  - `newPassword`: min 8 characters, max 72 bytes, no NUL byte, never trimmed.
- None of the three present → `400 BAD_REQUEST`
  `At least one of username, email, or newPassword is required`.
- No current password and no lockout. Self-edit is allowed (the admin trust level).
- It never changes `isAdmin`, `status` or `organizationId`.
- **"Taken" checks** as in `auth/account.md`:
  - a field is checked only when it differs byte-wise;
  - the target's own row is excluded;
  - matching uses the collation;
  - a unique-index race applies nothing and answers the same `409 USERNAME_TAKEN` /
    `EMAIL_TAKEN`.
- **One transaction:**
  1. apply the changes;
  2. when `email` or the password changes, mark every unused reset token of the target as
     used, and revoke every active refresh token of the target with **`admin_edit`**
     (a deliberate divergence from Citadel);
  3. a username-only change does neither.
- Answers `201 { user }`, reloaded.
- Edge cases (only `null` fields, an identical value, a case-only username change, `isAdmin`
  sent) and testable rules (72-byte and NUL rejection, revocation and token invalidation
  only on email or password changes).

## Files to Change
- `docs/agents/specs/api/endpoints/admin-users/edit.md` — new.
