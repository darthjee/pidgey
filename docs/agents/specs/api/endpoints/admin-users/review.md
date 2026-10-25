# Admin User Review Endpoints

Purpose: define the admin review tools: `POST /admin/users/search.json`,
`POST /admin/users/{id}/approve.json` and `POST /admin/users/{id}/deny.json`.

Shared rules (guards, admin user item, `{id}` handling, operator log) are in
[admin-users.md](../admin-users.md). The user status lifecycle is in
[organizations/entities.md](../../../organizations/entities.md#user-status-lifecycle);
session revocation in [authentication/sessions.md](../../authentication/sessions.md#revocation-reasons).
Errors use [conventions/errors.md](../../conventions/errors.md).

These tools are always available, whatever `USER_APPROVAL_REQUIRED` says.

## `POST /admin/users/search.json`

Lists users, optionally filtered. A read: it changes nothing and writes no operator log line.

Request (both fields optional; an empty body `{}` or no body at all is fine):

```json
{ "q": "bob", "status": "pending" }
```

| Field | Rules |
| --- | --- |
| `q` | optional; string, max 255 characters (after trimming) |
| `status` | optional; one of `pending`, `approved`, `denied` |

- `null` means absent, for both fields.
- `q` is **trimmed**; empty after trimming (e.g. whitespace only) means **no filter**.
- `q` is a **substring match** on `username` **or** `email`, with the column collation (case-
  and accent-insensitive, see [auth.md](../auth.md#identifiers)). `%`, `_` and `\` in `q`
  are **escaped** before building the `LIKE` pattern, so `q` is always a **literal**
  substring (`q = "%"` matches only values containing `%`).
- `status` filters by `users.status`.
- When both filters are present they combine with **AND**. With neither, every user is
  returned.
- Results are ordered by **`id` ascending** and are **not paginated** (pagination is pending
  in [api/pending.md](../../pending.md); see the known limitation in
  [admin-users.md](../admin-users.md#known-limitations)).

Success: **`201`**:

```json
{
  "users": [
    {
      "id": 7,
      "username": "bob",
      "email": "bob@example.com",
      "isAdmin": false,
      "createdAt": "2026-10-17T12:00:00.000Z",
      "status": "pending",
      "organizationId": 3,
      "organizationName": "Bob's Org"
    }
  ],
  "currentUserId": 1
}
```

- Each entry is an [admin user item](../admin-users.md#admin-user-item).
- `currentUserId` is the caller's own id (from the session), so the UI can hide "deny" on the
  caller's own row.

| Case | Answer |
| --- | --- |
| Invalid `status` | `400 VALIDATION_FAILED` — `status must be one of the following values: pending, approved, denied` |
| `q` not a string, or longer than 255 characters | `400 VALIDATION_FAILED` (templates in [validation.md](../../conventions/validation.md#message-templates)) |
| Otherwise | `201` |

## `POST /admin/users/{id}/approve.json`

Moves a `pending` user to `approved`. Empty body (any body fields are dropped).

Success: **`200`**, `{ "user": <admin user item> }` with `status: "approved"`.

| Case | Answer |
| --- | --- |
| Unknown id | `404 NOT_FOUND` — `User not found` |
| User not `pending` | `422 USER_NOT_PENDING` — `Only pending users can be approved`; nothing changes |
| Otherwise | `200` |

- The update is **conditional**: `UPDATE users SET status = 'approved' … WHERE id = ? AND
  status = 'pending'`. If it changes **0 rows**, answer `422`, so an approve can never
  overwrite a concurrent deny.
- **Sessions are untouched:** the user's existing session passes the gate on its next
  request, with no re-login (see
  [authentication/gate.md](../../authentication/gate.md#effects-on-the-next-request)).
- **`denied` is final** in v1: approve refuses a `denied` user. Reinstating a denied user is
  a pending decision ([admin-users.md](../admin-users.md#pending-decisions)).
- A successful approve writes an [operator log line](../admin-users.md#operator-log).

## `POST /admin/users/{id}/deny.json`

Moves a user to `denied` and ends every session. Empty body (any body fields are dropped).

Success: **`200`**, `{ "user": <admin user item> }` with `status: "denied"`.

Checks, in order:

| Case | Answer |
| --- | --- |
| Unknown id | `404 NOT_FOUND` — `User not found` |
| `id` is the caller's own id (from the session) | `422 CANNOT_DENY_SELF` — `You cannot deny your own account`; nothing changes |
| Any current status, including already `denied` | deny (below); `200` |

In **one transaction**:

1. set `status = 'denied'`;
2. revoke every active refresh token of the user (`revoked_at IS NULL`) with reason
   `admin_denied` ([sessions.md](../../authentication/sessions.md#revocation-reasons)).

- This is a deliberate improvement over Citadel's two separate writes: the status and the
  revocation are never seen apart.
- Every session of the user is rejected by the gate on its next request.
- **Idempotent:** denying an already `denied` user answers `200` again and revokes whatever
  is still active.
- **Admins can deny other admins.** Only self-deny is refused.
- The row is **kept**, so the username and email stay taken.
- Deny does **not** touch the user's device-authorization requests or password-reset tokens:
  the device-authorization claim
  ([requester.md](../auth/device-authorization/requester.md#claim)) and reset-password
  ([recovery.md](../auth/recovery.md#post-authreset-passwordjson)) already re-check the
  user's status.
- The user's organization and its API tokens are not affected (see the pending decision in
  [organizations/entities.md](../../../organizations/entities.md#pending-decisions)).
- A successful deny writes an [operator log line](../admin-users.md#operator-log).

## Edge cases

- Search with `q = "%"` or `q = "_"`: a literal match, not a wildcard.
- Search with a whitespace-only `q`: no filter.
- Search with `status: null`: no status filter.
- **Approve racing deny** on the same pending user: exactly one wins. If deny commits first,
  approve's conditional update changes 0 rows and answers `422 USER_NOT_PENDING`; if approve
  commits first, deny then sets `denied` (deny accepts any status).
- Approve of an `approved` or `denied` user: `422`, nothing changes.
- Deny of self: `422`, nothing changes, the caller's sessions stay active.
- Deny of an already denied user: `200`.
- `{id}` values such as `0`, `-1`, `01`, `1.5`, `abc` → `400`; a huge number or an unknown id
  → `404` ([admin-users.md](../admin-users.md#id-path-parameter)).

## Testable rules

The implementation must cover at least:

- the guard order: `401` (not `400`) for an anonymous caller with a bad body, `401` for a
  `pending` caller, `403` for an `approved` non-admin;
- the `{id}` `400`/`404` boundary;
- search: `LIKE` escaping of `%`, `_` and `\`; the AND combination; ordering by `id`;
  `currentUserId`; the organization fields in each item; an empty body and no body returning
  every user;
- approve: the conditional update, `422` on a non-pending user with nothing changed, the
  user's existing session passing the gate afterwards;
- deny: self-deny `422`; idempotence; status and revocation in the same transaction; every
  session of the user rejected by the gate afterwards;
- one operator log line per successful approve and deny, with ids and the action only.

## Pending decisions

None. The shared ones are in [admin-users.md](../admin-users.md#pending-decisions).
