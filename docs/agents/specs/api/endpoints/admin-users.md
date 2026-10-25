# Admin User Endpoints

Purpose: index of the `/admin/users/*.json` endpoint specs (instance-admin user management)
and the rules they all share (guards, admin user item, `{id}` handling, operator log).

The admin user endpoints follow the Citadel contract ([conventions.md](../conventions.md)) and
the authentication model ([authentication.md](../authentication.md)). The admin's powers and
trust level are defined in [organizations/entities.md](../../organizations/entities.md#admin).
Errors use [conventions/errors.md](../conventions/errors.md).

| File | Content |
| --- | --- |
| [review.md](admin-users/review.md) | `POST /admin/users/search.json`, `POST /admin/users/{id}/approve.json`, `POST /admin/users/{id}/deny.json`. |
| [edit.md](admin-users/edit.md) | `POST /admin/users/{id}/edit.json`. |
| [recovery.md](admin-users/recovery.md) | `POST /admin/users/{id}/recovery-link.json`, `POST /admin/users/{id}/send-recovery-email.json`. |

## Guards

Every route goes through, in this order (see
[authentication/gate.md](../authentication/gate.md#guard-order)):

1. **Origin check**: every `/admin/users/*.json` route is `POST`
   ([conventions/origin-check.md](../conventions/origin-check.md)).
2. **Gate**: only `approved` users with a valid session pass; anyone else, including a
   `pending` caller, gets the gate's uniform `401`.
3. **Admin check**: `users.is_admin = true` as read by the gate; otherwise
   `403 FORBIDDEN` — `Admin access required`
   ([authentication/gate.md](../authentication/gate.md#admin-check)).
4. **`{id}` parsing** on the routes that take one (see [`{id}` path parameter](#id-path-parameter)).
5. **Body validation** ([conventions/validation.md](../conventions/validation.md)).
6. **Handler.**

- An anonymous caller with an invalid body gets the gate's `401`, never a `400`; an
  authenticated non-admin gets `403`, never a `400` or `404`.
- The review tools (the `status` filter of search, approve and deny) are **always
  available**, whatever `USER_APPROVAL_REQUIRED` says.
- The caller id (the acting admin) **always comes from the authenticated session**, never
  from the request.

## Admin user item

Every admin route that returns a user uses this shape. It is the only place `status` and
`createdAt` are exposed. It is Citadel's admin user item plus the user's organization, as
flat `camelCase` fields (the frontend ignores the extra fields):

```json
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
```

| Field | Content |
| --- | --- |
| `id` | `users.id`. |
| `username`, `email` | Stored values (as entered, after trimming). |
| `isAdmin` | `users.is_admin`. |
| `createdAt` | `users.created_at`, ISO 8601 UTC with milliseconds. |
| `status` | `pending`, `approved` or `denied`. |
| `organizationId`, `organizationName` | The user's organization (`users.organization_id` and its name). |

Never included: the password digest, any token, `updatedAt`, or any SMTP connection or API
token data.

## `{id}` path parameter

The routes `/admin/users/{id}/*.json` take the target user's id in the path.

| `{id}` | Answer |
| --- | --- |
| Does not match `^[1-9][0-9]*$` (e.g. `0`, `-1`, `01`, `1.5`, `abc`) | `400 BAD_REQUEST` — `Validation failed (numeric string is expected)` |
| Well-formed but no such user, or out of range for the column (e.g. a huge number) | `404 NOT_FOUND` — `User not found` |

The format check runs after the admin check and before body validation; the lookup runs in
the handler.

## Shared rules

- **Trimming and passwords:** as in [auth.md](auth.md#trimming): `username` and `email` are
  trimmed before validation; `newPassword` is never trimmed and follows the
  [password hashing](auth.md#password-hashing) rules (min 8 characters, max 72 bytes, no NUL
  byte, hashed with `password_hash()`). Identifiers follow [auth.md](auth.md#identifiers)
  (stored as entered, column collation, max 255 characters).
- An optional field that is **absent or `null`** is "not present". A present but invalid
  value is `400 VALIDATION_FAILED`.
- **Unknown fields are dropped**, including `isAdmin`, `status` and `organizationId` sent to
  edit: no admin endpoint changes them through a body field.
- **Success codes** follow Citadel: `201` for search, edit, recovery-link and
  send-recovery-email; `200` for approve and deny (see
  [conventions/format.md](../conventions/format.md#success-codes)).

## Operator log

Every **successful** admin action (edit, approve, deny, recovery-link, send-recovery-email)
writes **one line** to the application (operator) log, so admin power stays traceable:

- the acting admin's `users.id`;
- the target's `users.id`;
- the action (`edit`, `approve`, `deny`, `recovery-link`, `send-recovery-email`);
- for edit, the **names** of the changed fields (`username`, `email`, `password`).

It **never** includes values (usernames, emails), passwords, tokens or links. Search is a
read and writes **no** operator log line. Send-recovery-email's "not sent" and SMTP failure
lines follow the same rule (see [recovery.md](admin-users/recovery.md)).

### Error logging

- A **unique-index violation** (edit's lost "taken" race) is caught and mapped to its `409`
  **without logging the database driver message**: MySQL's message contains the duplicate
  value (a username or an email).
- The same applies to **every `500` path** of these endpoints: what is logged identifies the
  route, the acting admin's and the target's `users.id` and the error class or code, never the
  driver message, request body fields, usernames, emails, passwords, tokens or links.

## Known limitations

- Two admins can deny each other and leave the instance with no admin. Recovery is out of
  band, as for the first admin ([organizations/bootstrap.md](../../organizations/bootstrap.md#first-admin)).
- Search returns every matching user in one unbounded response (pagination stays pending in
  [api/pending.md](../pending.md)).
- Send-recovery-email's response time includes the SMTP round trip, bounded by the timeouts
  of the [send flow](../../email/send-flow.md).

## Pending decisions

- **Pending decision:** reinstating a denied user. Approve only accepts `pending` users, so
  `denied` is final in v1 ([review.md](admin-users/review.md#post-adminusersidapprovejson)).
- **Pending decision:** notifying the old email address on an admin email change.
- **Pending decision:** a persisted audit table of admin actions (v1 has the
  [operator log](#operator-log) only).
- **Pending decision:** forbidding an email and password change in the same admin edit.
- Each detail file lists its own.
