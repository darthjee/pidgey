# Review endpoints

Create `api/endpoints/admin-users/review.md` with:

- `POST /admin/users/search.json`:
  - `q` is optional; trimmed; empty after trimming means no filter; max 255 characters. It
    is a collation substring match on `username` OR `email`, with `%`, `_` and `\` escaped
    so it is always a literal match.
  - `status` is optional, one of `pending`/`approved`/`denied`; `null` means absent; an
    invalid value is `400 VALIDATION_FAILED`.
  - The filters combine with AND. An empty body or no body returns every user.
  - Results are ordered by `id` ascending and not paginated (link to `api/pending.md`).
  - Answers `201 { users, currentUserId }`.
- `POST /admin/users/{id}/approve.json`:
  - empty body, with a conditional update `WHERE id = ? AND status = 'pending'`; 0 rows →
    `422 USER_NOT_PENDING`;
  - sessions are untouched;
  - answers `200 { user }`;
  - `denied` is final.
- `POST /admin/users/{id}/deny.json`:
  - empty body; `404`, then self → `422 CANNOT_DENY_SELF`;
  - in one transaction, set `status = denied` and revoke every active refresh token with
    `admin_denied`;
  - idempotent for an already denied user;
  - admins can deny admins; the row is kept;
  - device-authorization requests and reset tokens are untouched, since their flows
    re-check status;
  - answers `200 { user }`.
- Edge cases (approve racing deny, self-deny, a literal `%` in `q`, a whitespace-only `q`)
  and testable rules.

## Files to Change
- `docs/agents/specs/api/endpoints/admin-users/review.md` — new.
