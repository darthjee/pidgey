# Frontend Testing and Linting

Purpose: define the frontend test, coverage and lint setup, and how the checks run.

## Specs

- Jasmine specs live in `frontend/specs/assets/js/`, mirroring `assets/js/` (one spec per source
  file), named `*Spec.js`. Jasmine picks them up with the glob `specs/**/*[sS]pec.js`.
- Shared test helpers live in `frontend/specs/support/` (e.g. `jsx-loader.mjs`, `fakeWindow`,
  `fetchSequence`).
- Coverage: `test` runs under nyc; `coverage` runs under c8 with an lcov report
  (`coverage/lcov.info`).

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
  (see [deployment/pipeline.md](../deployment/pipeline.md)).

See [testing](../testing.md).
