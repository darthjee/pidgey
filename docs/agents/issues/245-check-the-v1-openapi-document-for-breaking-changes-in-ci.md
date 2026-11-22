# Issue: Check the /v1 OpenAPI document for breaking changes in CI

## Description

- Parent epic: #219 (wave 2, created by #228).
- Spec: `docs/agents/specs/node-client/openapi.md`, section "`/v1` is additive-only". The rule is also stated in `docs/agents/api/endpoints/openapi.md` and in the document's `info.description`.
- #244 is merged: the `/v1` document lives at `source/openapi/v1.json` and is served at `GET /v1/openapi.json`.
- `backend_checks` runs `composer lint` on `darthjee/circleci_pidgey-base:0.0.4`, after `prepare_source`, which moves `source/*` to the project root. The job has no context, so it holds no credential.
- That image already copies a pinned third-party binary (Mailpit) in from its official image. It is rebuilt and pushed by hand with `make build-ci-base` / `make push-ci-base`.
- Owner: `infra`, with `product-owner` for the API doc.

## Problem

`/v1` is additive-only. Removing or renaming a field, changing a type or the meaning of a status or code, or making an optional field required needs a `/v2` and a new client major line. Nothing enforces that rule today: a breaking edit to `source/openapi/v1.json` (or a code change shipped together with its matching document change) passes CI.

## Expected Behavior

- A branch that removes a field, renames one, changes a type, makes an optional field required, or removes a documented status fails `backend_checks`.
- A branch that adds an optional request field, a response field or an error code passes.
- On `main` itself, and on a tag cut from `main`, the diff is empty and the check passes.
- Only the branch's own changes count: the base is the merge-base with `main`, so a branch that is behind `main` is not flagged for `main`'s newer additions.
- The check fails only at oasdiff's `ERR` level. `WARN` findings are printed but do not fail the build.
- The check runs whether or not `clients/` is touched, and never in a job that holds a credential.
- `make openapi-diff` runs the same check locally, against `origin/main`.

### Testing

- On the PR branch, the check passes (the document is unchanged, or changed additively).
- A manual check before the PR opens, recorded in the PR description:
  - a temporary edit that removes a field from `source/openapi/v1.json` makes the script (and `make openapi-diff`) fail;
  - an edit that adds an optional field passes.
- The script exits non-zero, with a clear message, when the base document cannot be read at the merge-base. The check never passes silently.

## Solution

1. **CI image:** add a pinned `tufin/oasdiff` stage to `dockerfiles/circleci_pidgey-base/Dockerfile` and copy its binary to `/usr/local/bin/oasdiff`, following the Mailpit pattern.
   - Add an `OASDIFF_VERSION` build arg.
   - Bump `CI_BASE_VERSION` to `0.0.5` in the `Makefile` and in `ci_base_image` in `.circleci/config.yml`.
   - The image is rebuilt and pushed by hand (`make build-ci-base && make push-ci-base`) before the PR can go green; this is a manual step for the maintainer.
2. **Shared script:** `scripts/check_openapi_breaking.sh <head document> [base ref]`.
   - It resolves the base with `git merge-base <base ref, default origin/main> HEAD`.
   - It extracts the base `source/openapi/v1.json` with `git show <merge-base>:source/openapi/v1.json` into a temp file.
   - It runs `oasdiff breaking <base> <head> --fail-on ERR` and fails on any `ERR` finding.
   - It fails with a clear message when the base document is missing.
3. **CI step:** add a step to `backend_checks` that runs the script on `openapi/v1.json` (its path after `prepare_source`). The job stays without a context.
4. **Local:** add a `make openapi-diff` target that fetches `origin` and runs the script, using a pinned `tufin/oasdiff` image through docker (the same `OASDIFF_VERSION`) when oasdiff is not installed on the host.
5. **Docs:**
   - `docs/agents/deployment/pipeline.md`: the new step in the `backend_checks` row.
   - `docs/agents/docker/images.md` and `docs/agents/docker/makefile.md`: the oasdiff binary, `0.0.5`, and the new target.
   - `docs/agents/api/endpoints/openapi.md`: next to the additive-only rule, how it is enforced, with a link to the pipeline.
   - `docs/agents/specs/node-client/openapi.md`: name `oasdiff breaking --fail-on ERR` as the as-built check, replacing "for example".

Out of scope:
- an override or escape hatch for a deliberate break (a break needs `/v2`, which is a new document);
- the client-side contract tests (#219).

## Benefits

- The additive-only rule is enforced by CI instead of review, so the Node clients built on `/v1` keep working against every later backend.
- The check runs on every branch, including branches that never touch `clients/`.
- The tool is pinned and runs only in a job with no credential, so it adds no supply-chain path to the deploy key.
