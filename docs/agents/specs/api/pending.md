# Pending API Decisions

Purpose: track API concerns that are explicitly not decided yet.

- **Pending decision — rate limits:** per-token/per-organization request limits and the
  `429` response format. This includes throttling the public auth endpoints
  ([login, register](endpoints/auth/session.md#brute-force) and
  [recover](endpoints/auth/recovery.md#post-authrecoverjson)), which have no
  application-level throttle in v1: the recover cooldown (`PASSWORD_RESET_COOLDOWN`) is the
  only v1 protection. Leaning towards a **per-IP throttle** (failures per window →
  `429 TOO_MANY_REQUESTS`, client IP resolved as in
  [conventions/client-ip.md](conventions/client-ip.md)). It also includes the SMTP
  connection **test email** ([endpoints/pidgey-admin/smtp-connections.md](endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson)),
  which has no rate limit in v1 and opens an outbound SMTP connection per call. A per-username
  login lockout is rejected (it lets anyone lock a victim out and reveals that the username
  exists). Until then, proxy-level rate limiting is recommended; the current production host cannot apply it, a known gap recorded in
  [deployment/known-gaps.md](../../deployment/known-gaps.md#no-proxy-rate-limiting), so production keeps
  `USER_APPROVAL_REQUIRED=true`.
- **Pending decision — size limits:** maximum request body size and maximum
  `subject`/`text`/`html` length.
- **Pending decision — idempotency:** whether `POST /v1/messages` accepts an
  `Idempotency-Key` header to avoid duplicate sends on client retries (relevant since
  sending is synchronous and not persisted).
- **Pending decision — pagination** for list endpoints. In v1 the list endpoints (SMTP
  connections, API tokens, see [endpoints/pidgey-admin.md](endpoints/pidgey-admin.md#shared-rules))
  are **unpaginated**, ordered by `id` ascending; admin user search returns every match. This
  item stays open for future growth.
- **Pending decision — SMTP host deny-list / allow-list (SSRF):** users choose the SMTP
  `host` and `port`, so the server can be made to open TCP connections to internal addresses,
  and the test email's error categories act as a reachability oracle (known limitation in
  [endpoints/pidgey-admin/smtp-connections.md](endpoints/pidgey-admin/smtp-connections.md#known-limitations)).
  Any host is allowed in v1, since self-hosted setups need internal relays and Mailpit. A
  configurable deny-list / allow-list is pending.
- **Pending decision — SMTP credential redirection:** a `PATCH` on an SMTP connection may
  change `host`, `port` or `encryption` while keeping the stored password, so a hijacked
  session can make the server send the stored password to another server (or in cleartext
  with `encryption: none`) through the test email (known limitation in
  [endpoints/pidgey-admin/smtp-connections.md](endpoints/pidgey-admin/smtp-connections.md#known-limitations)).
  Accepted in v1 (equal access; the password belongs to the organization). Pending: require
  `password` to be re-sent (or cleared) when `host`, `port` or `encryption` changes on a
  connection with credentials.
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
