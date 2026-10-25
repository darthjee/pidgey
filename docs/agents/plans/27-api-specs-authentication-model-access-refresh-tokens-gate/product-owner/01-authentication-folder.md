# Authentication spec folder
Turn `docs/agents/specs/api/authentication.md` into a short index of `api/authentication/`, and
write the detail files from issue #27's decisions and the Citadel reference (sections 3.1–3.6).

- `access-token.md`
  - JWT HS256 claims (`sub`, `sid`, `username`, `isAdmin`, `iat`, `exp`). `username` and
    `isAdmin` are informational only.
  - Signing uses the fixed header and the current `SECRET_KEY`.
  - Strict verification rules: three strict base64url segments; the header must be exactly
    HS256 (`none` and every other algorithm rejected, `kid`/`jku`/`x5u` ignored); `hash_equals`
    against the current key, then each `PREVIOUS_SECRET_KEYS` entry; typed claims; `exp > now`
    with no leeway, on the PHP clock in UTC.
  - Cookie attributes: `HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=ACCESS_TOKEN_TTL`.
    No `Domain`. Never in a response body. Logout clears it.
  - The key rotation procedure.
  - Why hand-rolled: the dependency policy, with the libraries that were considered.
- `sessions.md`
  - Refresh token: 48 random bytes, hex-encoded; lifetime `REFRESH_TOKEN_TTL`; stored as SHA-256
    in `refresh_tokens.token_hash`.
  - Sessions (`session_uuid`, `started_at`), and the actions that start a new one.
  - Atomic rotation with a conditional revoke; `0 rows` → rollback and `401`.
  - Revocation reasons table (`rotated`, `logout`, `password_reset`, `replay_detected`,
    `admin_denied`). Bulk revokes touch only active rows.
  - Replay detection, stating explicitly that a multi-tab race revokes every session.
  - Time comparisons take `now` from PHP as a bound parameter and never call `NOW()`.
  - Pending: cleanup (only rows past `expires_at` may be deleted).
  - Pending: refresh-token storage in the browser.
- `gate.md`
  - Guard order: origin check → gate → admin check → validation → handler.
  - The gate steps: one query joining `users` to `refresh_tokens`, with `status = 'approved'`.
  - The byte-identical `401` (envelope from `conventions/errors.md`); a DB error → `500`.
  - Effects on the next request.
  - The admin check: `403 FORBIDDEN "Admin access required"`.
  - User status at the gate (pending, approved, denied), linking to `organizations/entities.md`
    for the lifecycle.
  - The shared `user` payload (`id`, `username`, `email`, `isAdmin`, plus `refreshToken`); never
    `status` or the digest.
- `api-tokens.md`
  - Keep the current token rules (CSPRNG, `pdg_` prefix, SHA-256 at rest, shown once,
    `revoked_at`, `last_used_at`, `401`/`403`).
  - The only scope is `send`, and the `admin` scope is removed.
  - Disjoint credentials: Bearer only on `/v1`, the cookie only on the admin UI surface; the wrong
    credential is ignored and the request gets `401`.
  - Pending: finer-grained scopes, token expiration.
- `local-development.md`
  - No TLS in dev and no flag to turn off `Secure`; why the localhost secure-context exemption
    makes this work.
  - Caveats: use `localhost`, not a LAN IP or hostname; Safari may reject the cookie.
  - Production needs HTTPS (directly or behind a TLS-terminating proxy, with
    `TRUSTED_PROXY_HOPS`).

## Files to Change
- `docs/agents/specs/api/authentication.md`: becomes an index of the files below.
- `docs/agents/specs/api/authentication/access-token.md`: new.
- `docs/agents/specs/api/authentication/sessions.md`: new.
- `docs/agents/specs/api/authentication/gate.md`: new.
- `docs/agents/specs/api/authentication/api-tokens.md`: new.
- `docs/agents/specs/api/authentication/local-development.md`: new.
- `docs/agents/specs/api.md`: update the description of the `authentication.md` row.
