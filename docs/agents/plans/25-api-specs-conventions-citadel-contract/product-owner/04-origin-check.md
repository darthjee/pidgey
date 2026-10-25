# Origin check and CORS
Create `api/conventions/origin-check.md` from Citadel 2.4:

- Every `POST`/`PUT`/`PATCH`/`DELETE` (public routes included) goes through the origin check,
  before authentication; the four rules on `Sec-Fetch-Site`/`Origin`/`Host`; rejection
  `403 FORBIDDEN` "Cross-site request rejected".
- Non-browser clients (no `Origin`, no `Sec-Fetch-Site`) are allowed, so Bearer-token callers of
  `POST /v1/messages` pass.
- Trusted origins = `ALLOWED_ORIGINS` (comma-separated), falling back to the origin of
  `FRONTEND_BASE_URL`; neither set → CORS off, same-origin only. CORS, when on, is credentialed.
  Config names without the Citadel prefix; mark exact names as a pending decision if needed.
- This replaces the CSRF pending decision in `organizations/bootstrap.md` ("token header vs.
  SameSite + JSON content type"): state that CSRF protection is the origin check (plus the
  `SameSite=Strict` cookie defined by the authentication spec), and update that line to link
  here.

## Files to Change
- `docs/agents/specs/api/conventions/origin-check.md` — new.
- `docs/agents/specs/organizations/bootstrap.md` — resolve the CSRF pending decision by linking
  to the origin check.
