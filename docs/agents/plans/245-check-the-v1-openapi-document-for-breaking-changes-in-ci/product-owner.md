# Product Owner Plan: Check the /v1 OpenAPI document for breaking changes in CI

Main plan: [plan.md](plan.md)

## Shared contracts

What `infra` produces, and these docs describe as built:
- `oasdiff` pinned through `OASDIFF_VERSION` in the `Makefile`, at `/usr/local/bin/oasdiff` in `circleci_pidgey-base:0.0.5`;
- `scripts/check_openapi_breaking.sh <head document> [base ref=origin/main]`, which compares against `source/openapi/v1.json` at the merge-base with `main` and fails only on `ERR` (`oasdiff breaking --fail-on ERR`);
- the `backend_checks` step `OpenAPI breaking-change check`, with no context;
- `make openapi-diff`, which runs the same check locally, through docker when oasdiff is not installed;
- no escape hatch: a deliberate break needs `/v2`.

Read infra's final diff for the exact pinned tag before writing it into docs.

## Implementation Steps

### Step 1 — Pipeline and docker docs

- `docs/agents/deployment/pipeline.md`, Check jobs table: the `backend_checks` row becomes "`composer lint`, then the OpenAPI breaking-change check (`scripts/check_openapi_breaking.sh`: `source/openapi/v1.json` against its version at the merge-base with `main`, `oasdiff breaking --fail-on ERR`)", with a link to `api/endpoints/openapi.md#v1-is-additive-only`. Note that the job holds no context.
- `docs/agents/docker/images.md`:
  - The `circleci_pidgey-base` row lists the pinned `oasdiff` binary, copied from `tufin/oasdiff`.
  - If the Deploy tooling section enumerates the binaries, add oasdiff there too.
- `docs/agents/docker/makefile.md`:
  - Add the `openapi-diff` target row.
  - Add the `OASDIFF_VERSION` variable row.
  - `CI_BASE_VERSION` becomes `0.0.5`, and the `push-ci-base` history gets "`0.0.5` added the `oasdiff` binary".
- `docs/agents/folder-structure.md` and `docs/agents/architecture/repository-layout.md`: the `scripts/` row mentions the OpenAPI breaking-change script. Those two files belong to `architect`. Leave a note for `architect` in the PR if you do not edit them.

### Step 2 — API and spec docs

- `docs/agents/api/endpoints/openapi.md`, section "`/v1` is additive-only": replace the "A CI check ... is planned" bullet with the as-built rule:
  - **Enforced in CI:** `backend_checks` diffs the document against its version at the merge-base with `main` and fails on any `ERR`-level breaking change (`oasdiff breaking --fail-on ERR`). It runs on every branch and tag, whether or not `clients/` changes. Link to the pipeline doc.
  - **Locally:** `make openapi-diff`.
  - There is no override: a deliberate break needs a `/v2` document.
- `docs/agents/specs/node-client/openapi.md`, section "`/v1` is additive-only": the "Enforced in CI" bullet names `oasdiff breaking --fail-on ERR` against the merge-base instead of "for example", and links to the as-built doc.
- `docs/agents/specs/node-client/testing.md`, the "Backend, OpenAPI diff" bullet: if useful, name the step and the script.

## Files to Change
- `docs/agents/deployment/pipeline.md`: the `backend_checks` row.
- `docs/agents/docker/images.md`: the oasdiff binary in `circleci_pidgey-base`.
- `docs/agents/docker/makefile.md`: the `openapi-diff` target, `OASDIFF_VERSION`, `CI_BASE_VERSION` `0.0.5`.
- `docs/agents/api/endpoints/openapi.md`: the as-built enforcement.
- `docs/agents/specs/node-client/openapi.md`: name the actual check.
- `docs/agents/specs/node-client/testing.md`: optional wording sync.

## Notes
- Do not invent the pinned version: copy it from infra's `Makefile`.
