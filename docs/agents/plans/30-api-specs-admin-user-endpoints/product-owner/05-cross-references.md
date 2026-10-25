# Cross-references

Update the existing specs to point at the new files and record the resolved decisions:

- `api/endpoints.md`: add the `admin-users.md` row (`/admin/users/*.json`: admin user
  search, edit, approve, deny, recovery link and email).
- `api/endpoints/admin.md`: remove the "admin user management endpoints" pending entry and
  link `admin-users.md` instead.
- `api/endpoints/auth/recovery.md`:
  - replace the "#30" mentions with links to `admin-users/recovery.md`;
  - resolve the cooldown pending decision (the admin endpoints bypass the cooldown);
  - keep the outbox pending decision, noting that it also covers the admin
    send-recovery-email.
- `organizations/entities.md`:
  - replace the "#30" mentions with links to `admin-users.md`;
  - replace the indirect-access pending decision with the v1 measures (operator log, session
    revocation on admin email or password change, recovery link shown to the admin and
    documented as part of the trust level);
  - list the remaining pending decisions there (notify the old email, audit table, combined
    change), linking to `admin-users.md`.
- `api/authentication/sessions.md`: add the `admin_edit` reason to the revocation reasons
  table (admin edit changing email or password: every active token of the user).
- `organizations/data-model/refresh-tokens.md`: add `admin_edit` to the `revoked_reason`
  values.
- `api/conventions/errors.md`: no new specific codes. Check that the `USERNAME_TAKEN` /
  `EMAIL_TAKEN` / `USER_NOT_PENDING` / `CANNOT_DENY_SELF` rows still match the new specs.
- `api/authentication/gate.md`: link the admin check to `admin-users.md`.

## Files to Change
- `docs/agents/specs/api/endpoints.md` — index row.
- `docs/agents/specs/api/endpoints/admin.md` — pending entry removed.
- `docs/agents/specs/api/endpoints/auth/recovery.md` — links, resolved cooldown decision.
- `docs/agents/specs/organizations/entities.md` — links, resolved indirect-access decision.
- `docs/agents/specs/api/authentication/sessions.md` — `admin_edit` reason.
- `docs/agents/specs/organizations/data-model/refresh-tokens.md` — `admin_edit` value.
- `docs/agents/specs/api/conventions/errors.md` — consistency check only.
- `docs/agents/specs/api/authentication/gate.md` — link.
