# Frontend Plan: Frontend: send organizationName on register

Issue: [40-frontend-send-organizationname-on-register.md](../../issues/40-frontend-send-organizationname-on-register.md)

## Overview
`POST /auth/register.json` requires `organizationName` (`docs/agents/specs/api/endpoints/auth/session.md`), but `AccountsClient.register` never sends it, so every registration answers `400 VALIDATION_FAILED`. The only live register form is the login modal's Register mode; `RegisterController` is orphaned (only its spec uses it).

## Context
- Rule to mirror: `organizationName` is required, a string not empty after trimming, max 255 characters. Names are not unique.
- API errors keep today's behavior: `error.message` becomes the form's submit error. `error.details` is **not** mapped to fields.
- The value is sent as typed; the API trims it. The client check uses the trimmed value.
- `password_confirmation` keeps being sent (the API drops it as an unknown field).

## Steps

- [01 — Client and validation](frontend/01-client-and-validation.md)
- [02 — Login modal field](frontend/02-login-modal-field.md)
- [03 — Delete RegisterController](frontend/03-delete-register-controller.md)

## CI Checks
- `frontend`: `npm run lint` (CI job: `frontend_checks`)
- `frontend`: `npm run coverage` (CI job: `frontend_checks`; `yarn test` locally)

## Notes
- Do not touch `docs/agents/specs/frontend/**`: #32 rewrites those specs. Do not change any API spec.
- Out of scope: showing the organization elsewhere (#42, #45), mapping `error.details` to fields, the single-flight refresh (#41), and the Vite `*.json` proxy (#46). Without #46, a manual test in the Vite dev server cannot reach `/auth/*.json`; rely on the Jasmine specs.
