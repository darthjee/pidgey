# Infra Plan: Set up CircleCI with lint, tests and Codacy coverage

Main plan: [plan.md](plan.md)

## Shared contracts

Implement exactly what is listed in [plan.md](plan.md#shared-contracts): image name/tag and
context, Makefile targets and variables, job steps, CI DB env vars (root user), triggers,
`CODACY_PROJECT_TOKEN`, `.codacy.yml`.

## Steps

- [01 — CI image Dockerfile and Makefile targets](infra/01-ci-image.md)
- [02 — CircleCI config](infra/02-circleci-config.md)
- [03 — Codacy config](infra/03-codacy-config.md)
- [04 — Setup docs](infra/04-setup-docs.md)

## CI Checks

- `source/`: `make tests` (`composer lint && composer tests` in the `tests` container) — mirrors
  CI jobs `backend_checks` and `backend_tests`.
- `.circleci/config.yml`: `circleci config validate` if the CLI is available.

## Notes

- The image must be built and pushed (`make build-ci-base && make push-ci-base`) before the
  pipeline can run; this is a manual step for the repo owner (credentials needed). The PR
  description must call it out, together with enabling the project on CircleCI/Codacy and
  setting `CODACY_PROJECT_TOKEN`.
- After `prepare_source` flattens `source/`, Clover paths are `lib/...` while repo paths are
  `source/lib/...`. Codacy matches by path suffix (works for Tent); if coverage does not show
  up, revisit.
- `mysql:9` may take a while to accept connections; `composer db:wait` (default 60s,
  `DB_WAIT_TIMEOUT`) handles it — raise the timeout in CI env if needed.
