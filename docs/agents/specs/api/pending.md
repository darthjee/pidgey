# Pending API Decisions

Purpose: track API concerns that are explicitly not decided yet.

- **Pending decision — rate limits:** per-token/per-organization request limits and the
  `429` response format.
- **Pending decision — size limits:** maximum request body size and maximum
  `subject`/`text`/`html` length.
- **Pending decision — idempotency:** whether `POST /v1/messages` accepts an
  `Idempotency-Key` header to avoid duplicate sends on client retries (relevant since
  sending is synchronous and not persisted).
- **Pending decision — pagination** for list endpoints.
- **Pending decision — CORS:** the admin UI is served from the same origin, so CORS is
  not needed for it; third-party browser clients are not supported unless decided.
