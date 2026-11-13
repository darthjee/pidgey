# Frontend Plan: Frontend alignment of the SMTP screens with the real API

Main plan: [plan.md](plan.md)

## Shared contracts

These are the parts of [plan.md](plan.md#shared-contracts) that concern this agent:

- **Messages:**
  - `ENCRYPTION_REQUIRED_MESSAGE = 'username and password require encryption tls or ssl'`;
  - `REDIRECTION_MESSAGE = 'password must be sent again when host, port or encryption changes'`;
  - both go on the `password` field, from the client-side check and from `mapValidationDetails`.
- **Deactivate:**
  - `SmtpConnectionsClient.deactivate(id)`: `POST /smtp-connections/:id/deactivate.json`, no
    body;
  - `DEACTIVATE_CONFIRM_MESSAGE` as in plan.md.
- **Fixtures:**
  - `frontend/specs/support/fixtures/smtpConnections/{item,list,testSent,validationFailed,smtpFailure,sendTimeFailure,tooManyRequests,notFound}.json`;
  - byte-equal copies of the bodies in plan.md, which product-owner adds to the API spec;
  - loaded through `loadFixture(name)` from `frontend/specs/support/loadFixture.js`.

## Steps

- [01 — Fixtures and loader](frontend/01-fixtures-and-loader.md)
- [02 — Deactivate](frontend/02-deactivate.md)
- [03 — Form rules](frontend/03-form-rules.md)
- [04 — Test-email modal](frontend/04-test-email-modal.md)

## CI Checks

- `frontend`: `yarn lint && yarn coverage` (or `make tests`, which runs them in `frontend_dev`).
  CI job: `frontend_checks`, which runs `npm run lint` and `npm run coverage`.

## Notes

- Do not run the manual end-to-end check. It is a pre-merge checklist in the PR description, run
  by the owner once #166 is merged. List its steps there:
  - in `make dev-up` against the dev `mailpit`: create, edit, activate, deactivate and delete;
  - a test email, seen in the Mailpit UI;
  - a `400` path and a `502` path (a bad host).
- Keep the existing patterns:
  - controllers take injectable `client` and `confirm`;
  - an `undefined` client result means the session ended: return without touching state;
  - JSDoc on every function;
  - support helpers in `specs/support/` get their own `*Spec.js`.
