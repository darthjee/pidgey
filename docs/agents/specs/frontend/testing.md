# Frontend Testing and Linting

Purpose: define the frontend test, coverage and lint setup, and how the checks run.

## Specs

- Jasmine specs live in `frontend/specs/assets/js/`, mirroring `assets/js/` (one spec per source
  file), named `*Spec.js`. Jasmine picks them up with the glob `specs/**/*[sS]pec.js`.
- Shared test helpers live in `frontend/specs/support/` (e.g. `jsx-loader.mjs`, `fakeWindow`,
  `fetchSequence`).
- Coverage: `test` runs under nyc; `coverage` runs under c8 with an lcov report
  (`coverage/lcov.info`).

## API response fixtures

- Specs that need API responses load **shared JSON fixtures** instead of building response
  objects inline.
- SMTP connections: `frontend/specs/support/fixtures/smtpConnections/`, one file per
  response shape of
  [smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md):
  - an SMTP connection item;
  - the list;
  - `400 VALIDATION_FAILED`;
  - an `SMTP_*` failure with `error.smtp`;
  - a `409` send-time failure;
  - `429 TOO_MANY_REQUESTS`;
  - `404 NOT_FOUND`.
- **Source:** the fixtures are **hand-maintained copies of the API spec's example
  responses**, matching them exactly. They are not captured from the backend: the backend
  endpoint tests already assert the same shapes, and capturing would couple the `source/`
  tests to `frontend/`.
- **Sync rule:** a PR that changes an example response in the API spec updates the matching
  fixture in the same PR.
- The check against the real backend is the manual end-to-end check below.

## SMTP connections definition of done

On top of the [definition of done](../testing/definition-of-done.md), the SMTP connection
screens ([screens.md](screens.md#smtp-connections-screens)) are done when:

- their Jasmine specs use the [shared fixtures](#api-response-fixtures);
- [api-compatibility.md](api-compatibility.md#pidgey-admin) is accurate: the deactivate row,
  `429` noted, and ✅ only where the call matches the spec;
- a **manual end-to-end check** in `make dev-up`, against the development `mailpit`, passes:
  create, edit, activate, deactivate, send a test email (delivered and seen in the Mailpit
  UI), a `400` path, a `502` path (a bad host), and delete.

## Linting

ESLint 9 flat config (`eslint.config.mjs`) with plugins complexity, jsdoc, react, react-hooks,
jasmine; it lints `assets` and `specs`.

## `package.json` scripts

| Script | Action |
| --- | --- |
| `build` | `vite build` (see [build.md](build.md)) |
| `server` | `vite dev` on port 8080 inside the container (host 8030; see [development.md](development.md)) |
| `test` | Run Jasmine under nyc |
| `coverage` | Run Jasmine under c8, lcov report → `coverage/lcov.info` |
| `lint` | `eslint assets specs` |
| `lint_fix` | `eslint assets specs --fix` |

## How checks run

- Locally: `make tests` runs `yarn lint` and `yarn coverage` in `frontend_dev`
  (see [docker/makefile.md](../docker/makefile.md)).
- CI: the `frontend_checks` job runs lint and coverage
  (see [deployment/pipeline.md](../../deployment/pipeline.md#check-jobs)).

See [testing](../testing.md).
