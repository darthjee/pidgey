# Issue: Deploy: add cleanup step with darthjee/scripts 0.12.0

## Description

Follow-up to #99. The deploy workflow ships without a `cleanup` step because `deploy_frontend.sh` comes from `darthjee/scripts:0.11.0`, which has no `cleanup` action. That action is tracked upstream in darthjee/docker#244, to be released as `darthjee/scripts:0.12.0`.

**Blocked on darthjee/docker#244.** As of 2026-10-31, `0.12.0` is not published (the latest Docker Hub tag is `0.11.0`).

## Problem

A deploy workflow that fails before the swap leaves its staging folder (`${SSH_REMOTE_TEMP_DIR}-<workspace id>`) on the server. Nothing removes these folders automatically. For now they are removed by hand, following the runbook in `docs/agents/deployment/operations.md`.

## Expected Behavior

- After a successful swap, the `release` job runs `deploy_frontend.sh cleanup`.
- `cleanup` removes other staging folders older than one day and keeps recent ones, which may belong to a deploy in progress.
- `cleanup` is a plain step: if it fails, the job and the workflow fail, even though the new release is already live, so the leftover folders get noticed.
- The specs and agent docs no longer describe `cleanup` as missing.

## Solution

Start only once `darthjee/scripts:0.12.0` is published (darthjee/docker#244).

- **CI image** (`dockerfiles/circleci_pidgey-base/Dockerfile`): bump `ARG SCRIPTS_VERSION` from `0.11.0` to `0.12.0`. The Dockerfile copies `deploy_frontend.sh` from `darthjee/scripts:${SCRIPTS_VERSION}`.
- **Makefile**: bump `CI_BASE_VERSION` from `0.0.2` to `0.0.3`, then build and push the image with `make build-ci-base push-ci-base`.
- **`.circleci/config.yml`**: point the `ci_base_image` anchor at `darthjee/circleci_pidgey-base:0.0.3`, and add a `Cleanup` step running `deploy_frontend.sh cleanup` as the last step of the `release` job, after `Release`. No `when: always` and no error tolerance.
- **Specs**:
  - `docs/agents/specs/docker/images.md`: the deploy tooling version becomes `0.12.0`.
  - `docs/agents/specs/docker/makefile.md`: `CI_BASE_VERSION` becomes `0.0.3`.
  - `docs/agents/specs/deployment/release.md` and `edge-cases.md`: drop the "until `0.12.0`" / "if it is not released in time" notes.
- **Agent docs** (from #101):
  - `docs/agents/deployment/pipeline.md`: replace the "No `cleanup`" note and add `cleanup` to the action table.
  - `docs/agents/deployment/known-gaps.md`: remove the "No `cleanup` action" section.
  - `docs/agents/deployment/operations.md`: keep "Removing stale staging folders", reworded as a fallback for when the `cleanup` step fails, or for removing a folder younger than one day that is known to be dead.
- **README**: check the CI image version note.

## Benefits

- Stale staging folders are removed automatically after each successful deploy.
- The "No `cleanup` action" known gap goes away, and the manual runbook becomes a fallback only.
