# Infra Plan: Build the npm publish pipeline of the clients

Main plan: [plan.md](plan.md)

## Shared contracts

- Reads `clients/<dir>/CHANGELOG.md` and requires a line matching `^## <version>( |$)` (dots escaped). The skeletons from `nodejs`/`cli` only have `## Unreleased`, so the gate fails until #254 adds `## 0.1.0`. That is intended.
- Tag → package: `pidgey-client-v<semver>` → `clients/nodejs` / `pidgey-client`; `pidgey-cli-v<semver>` → `clients/cli` / `pidgey-cli`.
- Pack jobs persist exactly one `release/*.tgz` to the workspace root; publish jobs attach it at `/tmp/workspace`.
- `pidgey-npm` context holds only `NPM_TOKEN`, attached only to the publish jobs.

## Steps

- [01 — Release pack script](infra/01-release-pack-script.md)
- [02 — Release publish script with dry-run mode](infra/02-release-publish-script.md)
- [03 — CircleCI jobs, filters and serial group](infra/03-circleci-jobs.md)
- [04 — Provenance research and decision](infra/04-provenance.md)
- [05 — Deployment docs and spec update](infra/05-docs.md)
- [06 — Dry-run demonstration](infra/06-dry-run-demonstration.md)

## CI Checks
- `clients/`: `clients/scripts/checks.sh` (CI job: `clients_checks`)
- `.circleci/config.yml`: `circleci config validate` if the CLI is available locally; otherwise review the YAML carefully (anchors, `requires` on the `clients_checks` matrix)
- Scripts: `bash -n` and, if available, `shellcheck clients/scripts/release_*.sh`

## Notes
- The branch build of the PR must not run any of the four new jobs (filters `branches: ignore: /.*/`). Confirm this on the PR's pipeline.
- Never `set -x` in release scripts; never print `NPM_TOKEN` or the `.npmrc` contents.
- The `security` agent reviews the credential setup (context, `.npmrc` handling, job isolation) before the PR is marked ready.
- The maintainer checklist (npm granular token, `pidgey-npm` context with its expression restriction, 2FA) is manual and goes in the PR description, not in code.
