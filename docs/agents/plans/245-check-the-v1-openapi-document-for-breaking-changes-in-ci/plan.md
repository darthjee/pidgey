# Plan: Check the /v1 OpenAPI document for breaking changes in CI

Issue: [245-check-the-v1-openapi-document-for-breaking-changes-in-ci.md](../../issues/245-check-the-v1-openapi-document-for-breaking-changes-in-ci.md)

## Overview

Enforce the "`/v1` is additive-only" rule in CI. A pinned `oasdiff` binary is baked into the CI image (`circleci_pidgey-base` `0.0.5`). A shared script, `scripts/check_openapi_breaking.sh`, diffs `source/openapi/v1.json` against its version at the merge-base with `main`, failing on any `ERR`-level breaking change. `backend_checks` runs it on every branch and tag, and `make openapi-diff` runs it locally through a pinned `tufin/oasdiff` docker image. `product-owner` then records the as-built check in the pipeline, docker and API docs.

## Agents involved

- [infra](infra.md)
- [product-owner](product-owner.md)

## Shared contracts

- **Tool:** `oasdiff`, one pinned release, e.g. `v1.11.x`. Pick the latest stable tag of `tufin/oasdiff` at implementation time, and use that exact tag everywhere, never `latest`.
  - `Makefile` variable: `OASDIFF_VERSION=<tag>`, the single source of truth.
  - Dockerfile: `ARG OASDIFF_VERSION=<same tag>` (default kept equal; `build-ci-base` passes `--build-arg OASDIFF_VERSION=$(OASDIFF_VERSION)`).
  - In the CI image: `/usr/local/bin/oasdiff`.
- **CI image:** `darthjee/circleci_pidgey-base:0.0.5` (`CI_BASE_VERSION=0.0.5`, and `ci_base_image` in `.circleci/config.yml`). It is rebuilt and pushed by hand (`make build-ci-base && make push-ci-base`) before the PR can go green.
- **Script:** `scripts/check_openapi_breaking.sh <head document> [base ref]`.
  - `base ref` defaults to `origin/main`.
  - The base is `git merge-base <base ref> HEAD`. The base document is `git show <merge-base>:source/openapi/v1.json`.
  - The command is `oasdiff breaking <base> <head> --fail-on ERR`. `WARN` findings are printed, but only `ERR` fails.
  - **Exit codes:** `0` when there is no `ERR` finding. Non-zero on any `ERR` finding, on a missing base document or merge-base (with a clear message), or on a missing tool. It never passes silently.
  - When `oasdiff` is not on `PATH`, it runs `docker run --rm tufin/oasdiff:$OASDIFF_VERSION` on a temp work dir, mounted read-only, that holds both files.
- **CI step:** in `backend_checks`, after `prepare_source`, named `OpenAPI breaking-change check`, run on `openapi/v1.json`. The job keeps no context.
- **Make target:** `make openapi-diff` runs `git fetch origin main`, then the script on `source/openapi/v1.json`, with `OASDIFF_VERSION` exported.
- **Severity:** `ERR` only. **No escape hatch:** a deliberate break needs a `/v2` document.
