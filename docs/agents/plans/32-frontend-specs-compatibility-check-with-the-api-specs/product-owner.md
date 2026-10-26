# Product Owner Plan: Frontend specs: compatibility check with the API specs

Issue: [32-frontend-specs-compatibility-check-with-the-api-specs.md](../../issues/32-frontend-specs-compatibility-check-with-the-api-specs.md)

## Overview
Write `docs/agents/specs/frontend/api-compatibility.md`, rewrite `frontend/screens.md` and
`frontend/layout.md`, update the `frontend.md` index, and settle the "#32" notes left in the
API specs (`authentication/sessions.md`, `authentication.md`, `authentication/local-development.md`).
No code, no API contract change.

## Context
- The frontend was copied from Citadel. Its clients (`frontend/assets/js/client/*`) and routes
  (`frontend/assets/js/utils/routing/HashRouteResolver.js`) were checked against
  `docs/agents/specs/api/**` while the issue was enhanced. The findings and decisions are in
  the issue (Problem → Findings, Solution → Decisions).
- Follow-up issues (frontend code, out of scope here):
  - #40: `organizationName` on register
  - #41: single-flight refresh
  - #42: organization screen, `OrganizationClient` and `ApiClient.getJson`
  - #43: SMTP connections screen
  - #44: API tokens screen
  - #45: organization in the admin users list (optional)
  - #46: Vite dev proxy for `*.json` paths (found while planning; `vite.config.js` proxies only `/v1`)
- #31 is merged, so the Pidgey admin endpoints (`api/endpoints/pidgey-admin/*`) and
  `api/endpoints/operational.md` exist on main.
- Spec format rule (`docs/agents/specs.md`): title, `Purpose:` line, content, **Pending
  decisions**.

## Steps

- [01 — API compatibility matrix](product-owner/01-api-compatibility-matrix.md)
- [02 — Rewrite screens.md](product-owner/02-rewrite-screens.md)
- [03 — Rewrite layout.md](product-owner/03-rewrite-layout.md)
- [04 — API spec notes and cross-references](product-owner/04-api-spec-notes-and-cross-references.md)

## Notes
- The frontend agent may be consulted to double-check the matrix against the client code.
  The security agent should review step 02 (refresh-token storage, XSS mitigations,
  single-flight rule) and the `sessions.md` wording in step 04.
- If a spec turns out to be wrong while building the matrix, fix the API spec. None was found
  during enhancement. If the frontend is wrong, link an existing follow-up or note a new one
  in the matrix; never change frontend code.
- No CI covers docs; check relative links by hand (or with a small script) and run the
  stale-reference `grep` listed in step 04.
