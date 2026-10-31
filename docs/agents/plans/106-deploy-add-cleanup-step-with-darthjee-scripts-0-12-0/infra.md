# Infra Plan: Deploy: add cleanup step with darthjee/scripts 0.12.0

Main plan: [plan.md](plan.md)

## Overview
Bump the deploy tooling in `circleci_pidgey-base` from `darthjee/scripts:0.11.0` to `0.12.0`, release the image as `0.0.3`, and run `deploy_frontend.sh cleanup` after `deploy_frontend.sh release`. Update the specs and the agent deployment docs so they no longer describe `cleanup` as missing.

## Context
- `deploy_frontend.sh` is copied into the CI image from `darthjee/scripts:${SCRIPTS_VERSION}` (`ARG SCRIPTS_VERSION=0.11.0` in `dockerfiles/circleci_pidgey-base/Dockerfile`).
- `0.11.0` has no `cleanup` action. It is requested upstream in darthjee/docker#244, to ship as `0.12.0`: it removes other `${SSH_REMOTE_TEMP_DIR}-*` staging folders older than one day and keeps recent ones.
- `Makefile` has `CI_BASE_VERSION=0.0.2`, and `.circleci/config.yml` pins `ci_base_image: &ci_base_image darthjee/circleci_pidgey-base:0.0.2`.
- Decisions from the issue discussion: `cleanup` is a plain step, so a failure fails the job. The manual runbook stays, reworded as a fallback.

## Steps

- [01 — Bump the CI image to scripts 0.12.0](infra/01-bump-ci-image.md)
- [02 — Add the cleanup step to the release job](infra/02-add-cleanup-step.md)
- [03 — Update specs and deployment docs](infra/03-update-docs.md)

## CI Checks
- No CI job validates the `release` job before a tag is pushed. Check `.circleci/config.yml` with `circleci config validate` when the CLI is available.
- The first tagged deploy after merge exercises the new step for real.

## Notes
- **Blocker:** do not start until `darthjee/scripts:0.12.0` is on Docker Hub. Check with `curl -s "https://hub.docker.com/v2/repositories/darthjee/scripts/tags/0.12.0"`. Until then, nothing in this plan can be built or validated.
- Building and pushing `darthjee/circleci_pidgey-base:0.0.3` (`make build-ci-base push-ci-base`) needs Docker Hub credentials. If the implementing agent cannot push, the maintainer must push the image before merging. Otherwise CI pulls a tag that doesn't exist and every job fails.
- Confirm the final `cleanup` contract (action name, any required variables) against the released `scripts/0.12.0/home/sbin/deploy_frontend.sh` in darthjee/docker before wiring it in. Adjust the step's environment if #244 lands with a different interface.
