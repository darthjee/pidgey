# Cross-references and inherited Pending decisions

- `api/authentication.md`: link `endpoints/auth.md` instead of `endpoints.md`.
- `api/authentication/gate.md`: registrations start `approved` only when
  `USER_APPROVAL_REQUIRED` is exactly `false`; "#28" mentions → links to
  `endpoints/auth/session.md` where useful.
- `api/authentication/sessions.md`: "#28" mentions → links to `endpoints/auth/session.md`.
- `organizations/entities.md`: same `USER_APPROVAL_REQUIRED` wording.
- `organizations/bootstrap.md`: link the registration spec (`auth/session.md`,
  `organizationName`); **resolve** the `USER_APPROVAL_REQUIRED` default Pending decision
  (default `true`) and the denied-user enumeration one (accepted known limitation); add the
  system-organization marking to the setup-flow Pending decision.
- `organizations/data-model/refresh-tokens.md`, `password-reset-tokens.md`,
  `account-edit-lockouts.md`: point to `architecture/configuration.md` and the new endpoint
  specs instead of "`api/authentication.md` (#27)".
- Do **not** touch `frontend/screens.md` (#32).

## Files to Change
- `docs/agents/specs/api/authentication.md`
- `docs/agents/specs/api/authentication/gate.md`
- `docs/agents/specs/api/authentication/sessions.md`
- `docs/agents/specs/organizations/entities.md`
- `docs/agents/specs/organizations/bootstrap.md`
- `docs/agents/specs/organizations/data-model/refresh-tokens.md`
- `docs/agents/specs/organizations/data-model/password-reset-tokens.md`
- `docs/agents/specs/organizations/data-model/account-edit-lockouts.md`
