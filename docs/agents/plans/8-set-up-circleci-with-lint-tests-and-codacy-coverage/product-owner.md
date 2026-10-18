# Product-owner Plan: Set up CircleCI with lint, tests and Codacy coverage

Main plan: [plan.md](plan.md)

## Shared contracts

Record in the specs exactly what is listed in [plan.md](plan.md#shared-contracts): the CI
image (amd64 only, manual build/push via `build-ci-base`/`push-ci-base`), the two jobs and
their steps, root DB access in CI and why, triggers on all branches and tags,
`CODACY_PROJECT_TOKEN`, and `.codacy.yml`.

## Implementation Steps

### Step 1 — Update the pipeline spec

In `docs/agents/specs/deployment/pipeline.md`:
- `backend_tests` row: name the service images (`mysql:9` named `mysql`, `axllent/mailpit`
  named `mailpit`), say CI connects as MySQL root (and why), and that the prepare step is
  `composer db:prepare`.
- Workflow: `backend_checks` and `backend_tests` run in parallel on every branch **and tag**;
  `upload_fe_files`/`release` stay main-only future work.
- Mention the root `.codacy.yml` analysis exclusions and that `CODACY_PROJECT_TOKEN` lives
  only in CircleCI.

### Step 2 — Update the Docker/Makefile and layout specs

- `docs/agents/specs/docker/makefile.md`: remove the `ci-*` row; add `build-ci-base` and
  `push-ci-base` rows; add `CI_BASE_VERSION` to the variables table.
- `docs/agents/specs/docker/images.md`: `circleci_pidgey-base` row — amd64 only, built from
  the `source/` context with `make build-ci-base`/`make push-ci-base`.
- `docs/agents/specs/architecture/repository-layout.md`: add `.codacy.yml` (Codacy analysis
  exclusions) to the root table.

## Files to Change

- `docs/agents/specs/deployment/pipeline.md` — job details, triggers, Codacy config.
- `docs/agents/specs/docker/makefile.md` — drop `ci-*`, add CI image targets/variable.
- `docs/agents/specs/docker/images.md` — CI image arch and build commands.
- `docs/agents/specs/architecture/repository-layout.md` — `.codacy.yml` entry.

## Notes

- Spec changes land in the same change set, before/alongside infra's implementation
  (AGENTS.md rule 2).
