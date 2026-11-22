# Infra Plan: Check the /v1 OpenAPI document for breaking changes in CI

Main plan: [plan.md](plan.md)

## Shared contracts

- `OASDIFF_VERSION=<pinned tufin/oasdiff tag>` in the `Makefile`; the same default in `ARG OASDIFF_VERSION` of the CI Dockerfile; never `latest`.
- CI image `darthjee/circleci_pidgey-base:0.0.5`, with `oasdiff` at `/usr/local/bin/oasdiff`.
- `scripts/check_openapi_breaking.sh <head document> [base ref=origin/main]`:
  - base = `git show $(git merge-base <base ref> HEAD):source/openapi/v1.json`;
  - runs `oasdiff breaking <base> <head> --fail-on ERR`;
  - non-zero on an `ERR` finding, on a missing base or merge-base, or on a missing tool;
  - falls back to `docker run tufin/oasdiff:$OASDIFF_VERSION` when `oasdiff` is not on `PATH`.
- `backend_checks` step `OpenAPI breaking-change check`, run on `openapi/v1.json`, with no context.
- `make openapi-diff`.

## Steps

- [01 — Add oasdiff to the CI image](infra/01-add-oasdiff-to-the-ci-image.md)
- [02 — Write the breaking-change script and make target](infra/02-write-the-script-and-make-target.md)
- [03 — Run the check in backend_checks](infra/03-run-the-check-in-backend-checks.md)

## CI Checks

- `.circleci/config.yml`: validate with `circleci config validate` if the CLI is available, otherwise review the YAML (CI job: `backend_checks`).
- `scripts/check_openapi_breaking.sh`: run `bash -n` and `shellcheck` if available; run `make openapi-diff` on the branch, where it must pass.
- `docker compose config -q` (unchanged, sanity).

## Notes

- **Manual gate:** the PR cannot go green until `darthjee/circleci_pidgey-base:0.0.5` is built and pushed (`make build-ci-base && make push-ci-base`). That needs Docker Hub credentials, which the agent does not have. Say so in the PR description and flag it to the maintainer; do not push the image yourself.
- **Manual verification**, recorded in the PR description:
  - a temporary edit that removes a property from a response schema in `source/openapi/v1.json` makes `make openapi-diff` fail;
  - adding an optional property passes;
  - revert both edits before committing.
- `security` should take a quick look: a third-party binary enters the CI image. It is pinned, it is copied from the official image as Mailpit is, and it runs only in `backend_checks`, which has no context. `build_backend` and the context jobs also use this image, so the binary sits next to the deploy key in those jobs. It is never executed there, which matches the risk Mailpit already carries.
