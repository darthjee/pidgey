# Pending API Decisions

Purpose: track API concerns that are explicitly not decided yet.

- **Pending decision — rate limits:** per-token/per-organization request limits and the
  `429` response format. This includes throttling the public auth endpoints
  ([login, register](endpoints/auth/session.md#brute-force) and
  [recover](endpoints/auth/recovery.md#post-authrecoverjson)), which have no
  application-level throttle in v1: the recover cooldown (`PASSWORD_RESET_COOLDOWN`) is the
  only v1 protection. Leaning towards a **per-IP throttle** (failures per window →
  `429 TOO_MANY_REQUESTS`, client IP resolved through `TRUSTED_PROXY_HOPS`). A per-username
  login lockout is rejected (it lets anyone lock a victim out and reveals that the username
  exists). Until then, proxy-level rate limiting is recommended (see
  [deployment/production.md](../deployment/production.md)).
- **Pending decision — size limits:** maximum request body size and maximum
  `subject`/`text`/`html` length.
- **Pending decision — idempotency:** whether `POST /v1/messages` accepts an
  `Idempotency-Key` header to avoid duplicate sends on client retries (relevant since
  sending is synchronous and not persisted).
- **Pending decision — pagination** for list endpoints.
- **Pending decision — CORS for third-party browser clients:** the origin check and the
  CORS rules (trusted origins from `ALLOWED_ORIGINS` / `FRONTEND_BASE_URL`, off by default)
  are defined in [conventions/origin-check.md](conventions/origin-check.md). The admin UI is
  served from the same origin, so it needs no CORS. Third-party browser clients (e.g.
  calling `POST /v1/messages` from a browser) are rejected by the current rules, by design:
  API tokens must not live in browsers. Supporting them would need a new decision and must
  not be done by widening the trusted-origins list.
- **Pending decision — cache headers:** no cache headers (`X-Skip-Cache`, `Cache-Control`)
  are required for now, since Pidgey has no caching proxy in front of the API (see
  [conventions/format.md](conventions/format.md#cache-headers)). Revisit if a caching proxy
  is introduced, or if `Cache-Control: no-store` is wanted on credential-bearing responses
  (login, refresh, token creation).
