# Endpoints index, sending and admin files

Split `api/endpoints.md` per the granularity rule (issue decision 9).

- `api/endpoints.md` becomes a short index: title, `Purpose:`, a table linking
  `endpoints/sending.md`, `endpoints/auth.md`, `endpoints/admin.md`, and **Pending
  decisions** (None, or pointers to the files').
- `endpoints/sending.md`: the `POST /v1/messages` section moved **unchanged** (fix relative
  links for the new depth: `../conventions/errors.md`, `../../email/failures.md`).
- `endpoints/admin.md` (temporary, rewritten by #31): the organization,
  `smtp_connections` and `api_tokens` rows of the old admin table, with the superseded note;
  the Pending decisions on final paths, the "send test email" endpoint and admin user
  management (pointing to #30).
- **Remove** the `POST /v1/registration`, `POST/DELETE/GET /v1/session` rows and the
  "Session endpoints are session-only" line: replaced by `/auth/*.json`
  (`GET /v1/session` → `POST /auth/status.json` for user state, `GET /organization.json`
  in #31 for the organization).
- `api.md`: endpoints row → "Index of the sending, auth and admin endpoint specs."

## Files to Change
- `docs/agents/specs/api/endpoints.md` — becomes an index.
- `docs/agents/specs/api/endpoints/sending.md` — new (moved content).
- `docs/agents/specs/api/endpoints/admin.md` — new (temporary admin table).
- `docs/agents/specs/api.md` — endpoints row.
