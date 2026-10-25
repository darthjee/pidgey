# Create the conventions index and format rules
Turn `api/conventions.md` into a short index (title, `Purpose:`, table of the files below) and
create `api/conventions/format.md` with the request/response format rules from Citadel 2.1 and
section 1 "Paths":

- JSON requests and responses (`Content-Type: application/json`); body-parser failures answer
  `400`/`413`/`415` (detailed in `errors.md`).
- Field names in `camelCase` (replacing `snake_case`); dates as ISO 8601 UTC with milliseconds.
- `DELETE` requests may carry a JSON body (logoff).
- Path rule: every API path is root-relative and ends in `.json`, with no prefix
  (`/auth/login.json`, `/admin/users/search.json`, `/health.json`); `*.json` always reaches the
  API, anywhere in the tree, including under `/admin/`. Exception: the public sending API keeps
  `POST /v1/messages`.
- Many reads are `POST` on purpose (bodies never in query strings).
- Success codes: `201` for most `POST`s, `200` where stated, `204` for logoff; clients treat any
  2xx as success; endpoint specs state the exact code.
- Guard order for every request: origin check → gate (non-public routes) → admin check (admin
  routes) → body validation → handler (link to `api/authentication.md` for the gate/admin check).
- No cache headers are required (Pidgey has no caching proxy); point to the pending item.

## Files to Change
- `docs/agents/specs/api/conventions.md` — becomes the index of `api/conventions/`.
- `docs/agents/specs/api/conventions/format.md` — new.
