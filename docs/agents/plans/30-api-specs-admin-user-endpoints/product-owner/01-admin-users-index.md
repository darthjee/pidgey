# Admin users index

Create `api/endpoints/admin-users.md` with:

- `Purpose:` line and a table linking `admin-users/review.md`, `admin-users/edit.md` and
  `admin-users/recovery.md`.
- **Guards:**
  - the order is origin check (every route is `POST`), then the gate (only `approved`
    users), then the admin check (`403 FORBIDDEN` "Admin access required", link to
    `authentication/gate.md#admin-check`), then `{id}` parsing, then body validation, then
    the handler;
  - the review tools are always available, whatever `USER_APPROVAL_REQUIRED` says;
  - the caller id always comes from the session.
- **Admin user item:** `id`, `username`, `email`, `isAdmin`, `createdAt` (ISO 8601 UTC, ms),
  `status`, `organizationId`, `organizationName`. It never includes the digest, tokens,
  `updatedAt`, or SMTP/API token data.
- **`{id}`:**
  - anything not matching `^[1-9][0-9]*$` → `400 BAD_REQUEST`
    `Validation failed (numeric string is expected)`;
  - well-formed but unknown or out of range → `404 NOT_FOUND` `User not found`.
- **Shared rules:**
  - trimming of `username`/`email` and the password rules (link to `auth.md`);
  - absent or `null` means "not present";
  - unknown fields are dropped (`isAdmin`, `status`, `organizationId`);
  - success codes: `201` for search, edit and the recovery endpoints, `200` for approve and
    deny.
- **Operator log:** one line per successful admin action (edit, approve, deny,
  recovery-link, send-recovery-email). It carries the admin id, the target id, the action,
  and the changed field names for edit; never values, passwords, tokens or links.
- **Known limitations:**
  - two admins can deny each other, leaving no admin (out-of-band fix, link to
    `organizations/bootstrap.md`);
  - search is unbounded;
  - send-recovery-email latency includes SMTP.
- **Pending decisions:**
  - reinstating a denied user;
  - notifying the old email on an admin email change;
  - a persisted admin audit table;
  - forbidding a combined email and password change in one admin edit.

## Files to Change
- `docs/agents/specs/api/endpoints/admin-users.md` — new.
