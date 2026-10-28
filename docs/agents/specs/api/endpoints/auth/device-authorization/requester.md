# Device Authorization: Requester Endpoints

Purpose: define the endpoints called by the device that is not logged in: create a request,
then poll it until a session is claimed.

Shared rules (state machine, guards, `201` success codes, input rules, error answers, expiry
boundary, clock, known limitations) are in [device-authorization.md](../device-authorization.md).
The table is `authorization_requests`
([organizations/data-model/authorization-requests.md](../../../../organizations/data-model/authorization-requests.md));
settings are in [architecture/configuration.md](../../../../architecture/configuration.md).
Both endpoints are **public** (no gate) but go through the
[origin check](../../../conventions/origin-check.md).

## `POST /auth/authorization-requests.json`

Public. Creates a device-authorization request for `username`.

Request:

```json
{ "username": "bob" }
```

| Field | Rules |
| --- | --- |
| `username` | string, not empty (after trimming), max 255 characters; stored trimmed |

Success: **`201`**:

```json
{
  "uuid": "3f1e7a52-8c1b-4d0e-9a51-2b7f0c9d1e44",
  "pollToken": "c0ffee…(96 hex chars)",
  "expiresAt": "2026-10-25T13:00:00.000Z"
}
```

| Field | Content |
| --- | --- |
| `uuid` | Public id of the request, a random UUID v4. |
| `pollToken` | 48 bytes from a CSPRNG, hex-encoded (96 characters). Returned only here; stored only as `poll_token_hash = sha256(pollToken)` (hex). |
| `expiresAt` | `now + AUTHORIZATION_REQUEST_TTL` (default `3600` seconds, 1 hour). |

Behaviour, in order:

1. Look up the user by the trimmed `username` (the user may not exist). The lookup uses the
   column collation, like login ([auth.md](../../auth.md#identifiers)).
2. Compute the **rate limit**: count the requests created within the last
   `AUTHORIZATION_REQUEST_CREATE_WINDOW` seconds (default `60`; `created_at > now − window`)
   with the **same client IP** and, separately, with the **same username**. **Both** counts
   always run (no short-circuit). The caller is **over the limit** when either count is
   **`>=`** `AUTHORIZATION_REQUEST_CREATE_LIMIT` (default `5`).
3. Generate `uuid`, `pollToken` and `expiresAt`.
4. **Over the limit:** return the payload anyway, **without saving anything**. Its poll
   token will always get a `404`. Unsaved creates do not count towards the limit.
5. If the user exists, enforce the **open cap**: when the user already has
   `>= AUTHORIZATION_REQUEST_MAX_OPEN_PER_USER` (default `5`) rows with `status = open`,
   flip the **oldest** of them (by `created_at`, then `id`) to `expired`
   (`resolved_at = now`) with a conditional update (`WHERE id = ? AND status = 'open'`).
   The new request is **never** rejected: if the eviction affects 0 rows (a concurrent actor
   changed that row), the insert still happens.
6. Insert the row: `status = open`, `username` (trimmed), `user_id` = the user's id or
   **`null`** when the username does not exist (such a row can never be approved),
   `poll_token_hash`, `expires_at`, `request_ip` and `request_user_agent` (resolved as in
   [conventions/client-ip.md](../../../conventions/client-ip.md): IP up to 45 characters,
   user agent truncated to 512 characters).
7. Return the payload.

The status of the user (`pending`, `approved`, `denied`) is not checked: a row is saved for
any existing user. A `pending` or `denied` user cannot pass the gate to approve it, and a
claim would answer `denied`.

| Case | Answer |
| --- | --- |
| Validation failure | `400 VALIDATION_FAILED` |
| Anything else (known or unknown username, under or over the limit) | `201`, the payload above |

### Enumeration safety

The status and the body shape are the **same** whether or not the username exists and
whether or not the caller is over the limit: an unknown username or an over-limit create
still returns a fresh `uuid`, `pollToken` and `expiresAt`. Only the timing can differ (see
the known limitation in [device-authorization.md](../device-authorization.md#known-limitations)).

## `POST /auth/authorization-requests/:uuid/poll.json`

Public. Called by the requester on an interval.

Request:

```json
{ "pollToken": "c0ffee…" }
```

| Field | Rules |
| --- | --- |
| `pollToken` | string, not empty; taken as sent |

Find the row by `uuid` **and** `poll_token_hash = sha256(pollToken)`. A malformed `:uuid`
simply finds nothing.

| Case | Answer |
| --- | --- |
| Validation failure | `400 VALIDATION_FAILED` |
| No row (unknown or malformed uuid, **or** wrong token: indistinguishable) | `404 NOT_FOUND` — `Authorization request not found` |
| `open` and `now >= expires_at` | Flip to `expired` with a conditional update (`WHERE uuid = ? AND status = 'open'`, `resolved_at = now`); `201 { "status": "expired" }` |
| `open` and `now < expires_at` | `201 { "status": "open" }` |
| `denied`, `expired` or `logged` | `201 { "status": "<that status>" }` |
| `approved` | Try to **claim** it (below) |

If the expiry flip affects 0 rows (a concurrent actor changed the row), the poll answers
`expired` anyway; the next poll reports the real state.

### Claim

The claim runs in **one transaction**:

1. Conditional update `approved → logged` (`WHERE uuid = ? AND status = 'approved'`,
   `logged_at = now`).
   - **0 rows affected** (another poll won the race): `201 { "status": "logged" }`, no
     credentials.
2. Look up the request's user (`user_id`) and check its status.
   - `user_id` is null or the user row is missing: commit; `201 { "status": "logged" }`, no
     credentials.
   - The user is not `approved` (`pending` or `denied`, e.g. set back after the approval):
     conditional update `logged → denied` (`resolved_at = now`); commit;
     `201 { "status": "denied" }`, with no session and no cookie. This is the same answer as
     an explicit deny.
3. The user is `approved`: **start a new session** (insert the first refresh token, see
   [authentication/sessions.md](../../../authentication/sessions.md#sessions)); commit.

If the session insert fails, the **whole transaction rolls back**, so the row is never left
`logged` without credentials having been issued: the answer is a `500 INTERNAL_ERROR`, the
row stays `approved`, and a later poll can claim it again.

The winning poll answers **`201`** with the
[shared `user` payload](../../../authentication/gate.md#shared-user-payload) plus `status`,
and sets the `access_token` cookie
([authentication/access-token.md](../../../authentication/access-token.md#cookie)) exactly
like login:

```json
{
  "status": "approved",
  "user": { "id": 7, "username": "bob", "email": "bob@example.com", "isAdmin": false },
  "refreshToken": "9f2c…(96 hex chars)"
}
```

Only the first poll that claims the request gets credentials. Every later poll (including the
requester's own next tick) gets `{ "status": "logged" }`. The session created by a claim
behaves exactly like a login session: rotation, replay detection, logoff and revocation all
apply.

## Edge cases

- Create for an unknown username: a row with `user_id = null` is saved (when under the
  limit); it can never be approved, and polls answer `open` until it expires.
- Create while over the limit: the payload is returned but nothing is saved; polls get `404`.
- Create for a `pending` or `denied` user: a row is saved; the user cannot pass the gate to
  approve it, and a claim would answer `denied`.
- Open cap reached: the oldest `open` row (by `created_at`, then `id`) is flipped to
  `expired`; the new request is always inserted.
- Poll with the right uuid and a wrong token, or a wrong uuid: the same `404`.
- Poll of an `open` row at exactly `expires_at`: flipped to `expired`.
- Two polls race on an `approved` row: one wins credentials, the other gets `logged`.
- Claim when the user was denied or set back to `pending` after the approval: `denied`, no
  session.
- Claim when the user row was deleted: `logged`, no credentials.

## Testable rules

The implementation must cover at least:

- enumeration safety of create: the same status and body shape for an existing and an
  unknown username, and under and over the limit;
- both rate-limit counts always run (per IP and per username), and the limit uses `>=`;
- an over-limit create saves nothing and its poll token answers `404`;
- the open-cap eviction of the oldest `open` row (ties broken by `id`), the new row always
  inserted;
- the poll `404` is identical for an unknown uuid, a malformed uuid and a wrong token;
- the lazy expiry flip at exactly `expires_at` (`now >= expires_at`);
- every state transition is conditional: a concurrent second actor loses (two polls racing
  on one `approved` row give exactly one set of credentials);
- the claim transaction rolls back when the session insert fails (the row stays `approved`);
- a claim for a `pending` or `denied` user answers `denied` with no session and no cookie.

## Pending decisions

None. (The verification code and the retention of rows are pending in
[device-authorization.md](../device-authorization.md#pending-decisions).)
