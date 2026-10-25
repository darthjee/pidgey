# Gate and Admin Check

Purpose: define the per-request authentication gate of the admin UI surface, the admin
check, user status at the gate and the shared `user` payload.

## Guard order

Every request on the cookie-authenticated surface goes through, in order (see also
[conventions/format.md](../conventions/format.md#guard-order)):

1. **Origin check** for `POST`, `PUT`, `PATCH`, `DELETE` ([origin-check.md](../conventions/origin-check.md)).
2. **Gate** on non-public routes (below).
3. **Admin check** on admin routes (below).
4. **Body validation** ([validation.md](../conventions/validation.md)).
5. **Handler.**

The public `/v1` API uses Bearer tokens instead of the gate (see
[api-tokens.md](api-tokens.md)).

## The gate

Every non-public route of the admin UI surface runs this check:

1. No `access_token` cookie → `401`.
2. Verify the JWT with the strict rules of [access-token.md](access-token.md#verification-strict-rules)
   (signature with the current key, then previous keys; `exp > now`).
3. `sid` must be a well-formed UUID and `sub` an integer.
4. Find the user with **one database query**: `users` joined to `refresh_tokens` on
   `user_id`, where `session_uuid = sid`, `revoked_at IS NULL`, `expires_at > now` (bound
   from PHP, see [sessions.md](sessions.md#clock)) and `users.status = 'approved'`. A row must
   exist and its user id must equal `sub`.
5. The authenticated user is `{ id: sub, sid, username, isAdmin }`, with `username` and
   `isAdmin` taken **from the database row**, never from the JWT. The user's
   `organization_id` also comes from the database and drives the
   [organization scope](../../organizations/scope.md).

A Bearer `Authorization` header is **ignored** by the gate: it never authenticates a cookie
route.

### Uniform `401`

Every failure in steps 1–4 answers the **same** `401`, byte for byte apart from the
`timestamp` (envelope in [conventions/errors.md](../conventions/errors.md)):

```json
{ "error": { "code": "UNAUTHORIZED", "message": "Invalid or expired access token" }, "statusCode": 401, "timestamp": "..." }
```

- This includes a missing cookie (step 1): unlike Citadel's `"Missing access token"`, Pidgey
  uses the one message for every gate failure. The frontend reacts to the status only.
- Callers cannot tell why a token was rejected (bad signature, expired, revoked session,
  user not approved, unknown user).
- Timing differences between "bad signature" (no query) and "no row" (one query) are
  accepted: the token is not secret to its holder.
- A **database error** in step 4 is a `500` (`INTERNAL_ERROR`), never a `401`, so a database
  outage does not make the frontend refresh or log the user out.

### Effects on the next request

Because step 4 reads the database on every request, these take effect on the **next
request**, not when the access token expires:

- logout (that session);
- password reset, replay detection and admin deny (every session of the user);
- admin approve (a pending user's existing session starts working);
- admin demotion (`is_admin` set to false out of band: admin routes start answering `403`).

## Admin check

Admin routes (`/admin/users/*.json`) additionally require `users.is_admin = true` **as read by
the gate**. An authenticated non-admin gets:

```json
{ "error": { "code": "FORBIDDEN", "message": "Admin access required" }, "statusCode": 403, "timestamp": "..." }
```

An anonymous caller gets the gate's `401` first. The admin's powers are defined in
[organizations/entities.md](../../organizations/entities.md#admin).

## User status at the gate

`users.status` is one of `pending`, `approved`, `denied` (lifecycle in
[organizations/entities.md](../../organizations/entities.md#user-status-lifecycle)).

- Only `approved` users pass the gate.
- New registrations start `pending` when `USER_APPROVAL_REQUIRED` is exactly `true`,
  otherwise `approved` (see [architecture/configuration.md](../../architecture/configuration.md)).
- `pending` users can log in and get a real session (tokens issued), but the gate blocks every
  protected route until an admin approves them. The status endpoint tells the frontend they
  are pending (#28).
- `denied` users look like unknown users everywhere, except a login with the **correct**
  password, which answers `403 USER_DENIED` ([conventions/errors.md](../conventions/errors.md#specific-codes)).

## Shared `user` payload

Login, register, refresh and the winning device poll all return this body and set the
`access_token` cookie:

```json
{
  "user": { "id": 7, "username": "bob", "email": "bob@example.com", "isAdmin": false },
  "refreshToken": "9f2c…(96 hex chars)"
}
```

- `user.status`, the password digest and the access token are **never** included.
- The endpoints that return it, and their errors, are specified in #28 (auth) and #29
  (device authorization).

## Pending decisions

None.
