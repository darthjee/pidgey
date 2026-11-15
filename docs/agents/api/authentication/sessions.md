# Refresh Tokens and Sessions

Purpose: define refresh tokens, sessions, atomic rotation, revocation reasons and replay
detection for the admin UI.

The refresh token is the long-lived credential of a session. It is stored in the
`refresh_tokens` table ([organizations/data-model/refresh-tokens.md](../../organizations/data-model/refresh-tokens.md)).
`REFRESH_TOKEN_TTL` is defined in
[architecture/configuration.md](../../architecture/configuration.md).

## Refresh token

- An opaque random string: **48 bytes from a CSPRNG, hex-encoded (96 characters)**.
- Valid for `REFRESH_TOKEN_TTL` seconds (default `604800`, 7 days):
  `expires_at = issued_at + REFRESH_TOKEN_TTL`.
- Returned in the JSON body of login, register, refresh and the winning device poll (see the
  [shared `user` payload](gate.md#shared-user-payload)). The frontend stores it and sends it
  back in request bodies as `{ "refreshToken": "..." }`.
- **Stored only as a SHA-256 hex hash** in `refresh_tokens.token_hash`. The plain value is
  never stored or logged.

## Sessions

A **session** is a chain of rotated refresh tokens sharing one `session_uuid` and one
`started_at`. The `session_uuid` is the `sid` claim of the access token (see
[access-token.md](access-token.md#claims)).

- Login, register and a winning device poll each **start a new session**: a new random UUID
  and `started_at = now`.
- Refresh **continues** the session: the new row copies `session_uuid` and `started_at`.
- A session has **at most one active row** (`revoked_at IS NULL`) and never forks.
- There is **no absolute session lifetime** in v1: `started_at` is recorded but not enforced,
  so a session refreshed at least every `REFRESH_TOKEN_TTL` lives indefinitely (see the
  pending decision below).

## Rotation

Before rotating, the presented token is looked up by its `token_hash`. Rotation proceeds only
when the row exists, is **unrevoked** (`revoked_at IS NULL`) and **unexpired**
(`expires_at > now`), and its user is not `denied`. Every other outcome follows
[Replay detection](#replay-detection) (and the `admin_denied` rule for a denied user); the
endpoint's error payloads are in
[endpoints/auth/session.md](../endpoints/auth/session.md#post-authrefreshjson).

Refresh then rotates the presented token, in **one transaction**:

1. Insert a new row with the same `session_uuid` and `started_at`, a fresh token and
   `issued_at = now`.
2. Revoke the presented row with reason `rotated`, **conditionally**:
   `WHERE id = ? AND revoked_at IS NULL AND expires_at > ?` (`now` bound from PHP, see
   [Clock](#clock)).
3. If the conditional revoke affects **0 rows**, a concurrent refresh already won (or the
   token expired in between): roll back the transaction and answer a plain `401`, with **no
   side effect** (no replay revocation).

Request and response payloads and the other refresh errors are in
[endpoints/auth/session.md](../endpoints/auth/session.md#post-authrefreshjson).

## Revocation reasons

Revocation always sets `revoked_at` **and** `revoked_reason` together.

| Reason | Written by |
| --- | --- |
| `rotated` | Refresh, on the presented token. |
| `logout` | Logout, on every active token of the presented token's session. |
| `password_reset` | Password reset, on every active token of the user. |
| `replay_detected` | Refresh, when a `rotated` token is presented again: every active token of the user. |
| `admin_denied` | Admin deny: every active token of the user. Refresh or status of a denied user: the presented token. |
| `admin_edit` | Admin edit changing the user's email or password: every active token of the user ([endpoints/admin-users/edit.md](../endpoints/admin-users/edit.md)). |

Bulk revokes only touch **active** rows (`revoked_at IS NULL`), so already-revoked rows keep
their original reason.

## Replay detection

- Presenting a token already revoked with reason `rotated` means it was stolen or replayed:
  every active token of that user is revoked with `replay_detected`, and the request answers
  `401`.
- A token revoked with any other reason, an expired token or an unknown token answers a plain
  `401` with no side effect.
- There is **no grace window**: re-presenting a `rotated` token is always a replay.
- **Multi-tab race (accepted):** when two tabs refresh with the same token, two cases exist:
  - **After the winner committed:** the other tab's request looks the token up after the
    winning refresh committed, finds it `rotated`, and it is treated as a replay: **every
    session of the user is revoked** and the user must log in again.
  - **Strictly concurrent:** both requests pass the lookup before either commits; the loser's
    conditional revoke affects 0 rows (see [Rotation](#rotation)), so it rolls back and gets a
    plain `401` **without** replay revocation. This gap is accepted.

  This strict rule is kept on purpose; the frontend must run a single refresh at a time,
  within a tab and across tabs (see the single-flight rule in
  [frontend/screens.md](../../frontend/screens.md#refresh-single-flight); the frontend change
  is follow-up #41).

## Clock

- Every time comparison (`expires_at`, `issued_at`, `started_at`, JWT `exp`) uses **PHP's
  clock in UTC**, read through `Pidgey\Infra\WallClock` (see
  [request-handler/request-response/errors.md](../../request-handler/request-response/errors.md#wall-clock)).
- SQL receives `now` as a **bound parameter**; queries never call `NOW()` or other database
  clock functions, so PHP and MySQL timezones or clocks cannot disagree.

## Effects on the gate

The gate checks the session in the database on every request (see [gate.md](gate.md)), so
logout, password reset, replay detection, admin deny and an admin email or password change
take effect on the **next request**,
not when the access token expires.

## Pending decisions

- **Pending decision:** cleanup of `refresh_tokens`. Nothing purges rows in v1. A future purge
  must delete **only rows past `expires_at`**: revoked-but-unexpired `rotated` rows are needed
  for replay detection.
- **Pending decision:** refresh-token storage in the browser. Following the Citadel contract,
  the frontend keeps the refresh token and sends it in request bodies, which makes it readable
  by XSS. #32 reviewed it and kept `localStorage` for v1 (Citadel parity) as a known
  limitation, with the XSS mitigations listed in
  [frontend/screens.md](../../frontend/screens.md#refresh-token-storage). An `HttpOnly`
  refresh cookie scoped to the refresh path stays pending.
- **Pending decision:** an absolute session lifetime: a cap (e.g. a `SESSION_MAX_AGE` setting)
  checked against `started_at` on refresh, after which the session must log in again.
