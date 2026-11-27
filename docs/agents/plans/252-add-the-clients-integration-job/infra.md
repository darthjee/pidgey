# Infra Plan: Add the clients_integration job

Main plan: [plan.md](plan.md)

## Shared contracts

Produces everything in [plan.md](plan.md#shared-contracts) except the specs themselves: the `integration_app` service, the six environment variables, `clients/scripts/integration_support.mjs`, and the call to `yarn test:integration` in `clients/nodejs` then `clients/cli`.

## Steps

- [01 — Add the integration app service](infra/01-add-integration-app-service.md)
- [02 — Write the seed script and the shared spec helper](infra/02-seed-script-and-helper.md)
- [03 — Write integration.sh and the Makefile target](infra/03-integration-script-and-makefile.md)
- [04 — Add the clients_integration CircleCI job](infra/04-circleci-job.md)
- [05 — Update the infra docs](infra/05-infra-docs.md)

## CI Checks

- `clients/`: `make clients-checks` (CI job: `clients_checks`), still green: the new script files are outside the packages.
- `clients/` end-to-end: `make clients-integration` (CI job: `clients_integration`).
- `.circleci/config.yml`: validate with `circleci config validate` when available.

## Notes

- Machine executor bind mounts: `docker_volumes/vendor` and the clients' `node_modules` volumes are written by container users (`app`, `node`) whose uid may differ from the CircleCI machine user. If the install fails on permissions, `chmod -R a+rwX docker_volumes` in a CI step before `make clients-integration`.
- Node's `fetch` sends neither `Origin` nor `Sec-Fetch-Site`, so the seed passes the origin check (rule 3). A `403 FORBIDDEN` from the seed means a header crept in.
- Keep tokens out of logs: the seed prints `KEY=value` lines to a mode-600 temp file, never to the job output.
