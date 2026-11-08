# Infra Plan: Email test infrastructure: Mailpit instances, TLS fixtures and CI

Main plan: [plan.md](plan.md)

## Shared contracts

These are the infra parts of [plan.md#shared-contracts](plan.md#shared-contracts):

- the Mailpit version `v1.32.0`;
- the instance table (modes, binds, auth);
- the TLS fixture file names and SANs;
- the env key table (compose and CI columns);
- the auth literal `pidgey-test:pidgey-test-password`;
- the CI base image release step.

The backend agent relies on the instances answering on the listed ports, and on the
`TEST_MAILPIT_*` keys being set in both environments.

## Steps

- [01 — TLS fixtures](infra/01-tls-fixtures.md)
- [02 — Compose and .env](infra/02-compose-and-env.md)
- [03 — CI base image](infra/03-ci-base-image.md)
- [04 — CircleCI backend_tests](infra/04-circleci-backend-tests.md)
- [05 — Infra docs](infra/05-infra-docs.md)

## CI Checks

- `source`: `make tests` (CI jobs: `backend_checks`, `backend_tests`).

## Notes

- Do not run `make push-ci-base`; the owner pushes the image. Run `make build-ci-base` to
  verify that the Dockerfile builds, if Docker is available.
- The CI step must fail the job when an instance does not answer, rather than letting the
  tests fail later.
