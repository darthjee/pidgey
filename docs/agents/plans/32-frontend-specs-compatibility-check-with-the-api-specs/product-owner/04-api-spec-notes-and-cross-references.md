# API spec notes and cross-references
Settle the notes in the API specs that deferred to #32, and remove stale references.

- **`api/authentication/sessions.md`:**
  - **Multi-tab race:** replace "(single-flight, checked in #32)" with a link to the
    single-flight rule in `frontend/screens.md` and follow-up #41.
  - **Pending decision on refresh-token storage:** reword it. #32 reviewed it and kept
    `localStorage` for v1 (Citadel parity), with the XSS mitigations in `frontend/screens.md`.
    The `HttpOnly` refresh cookie scoped to the refresh path stays pending.
- **`api/authentication.md`:** update the pending line "Refresh-token storage in the browser
  (XSS exposure, revisited with #32)" to match the `sessions.md` wording.
- **`api/authentication/local-development.md`:** replace "Implementing and checking this is a
  follow-up in #32" with two points:
  - the Vite `*.json` proxy is follow-up #46;
  - the origin check accepts the proxied dev origin, because the browser sends
    `Sec-Fetch-Site: same-origin` (rule 2 of `conventions/origin-check.md`).
- **`frontend/development.md`:** in "API access", replace "done by a later implementation
  issue" with a link to #46.
- **Stale-reference check:** `grep -rn "/v1/session\|PHP session\|SessionClient\|OrganizationsClient\|#32" docs/agents/specs`.
  - No hits may remain, except `api/authentication.md`'s "There is no PHP session" and
    statements that refer to #32's outcome.
  - Fix any other hit.
- **Link check:** check every relative link in the files changed by steps 01–04.

## Files to Change
- `docs/agents/specs/api/authentication/sessions.md` — multi-tab note and storage pending decision.
- `docs/agents/specs/api/authentication.md` — pending line.
- `docs/agents/specs/api/authentication/local-development.md` — dev proxy and origin note.
- `docs/agents/specs/frontend/development.md` — link #46.
