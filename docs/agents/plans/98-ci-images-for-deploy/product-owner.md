# Product Owner Plan: CI images for deploy

Main plan: [plan.md](plan.md)

## Shared contracts

- infra produces `darthjee/circleci_pidgey-base:0.0.2` (`CI_BASE_VERSION=0.0.2`) with `deploy_frontend.sh` from `darthjee/scripts:0.11.0`, `openssh-client` and `rsync`.

## Implementation Steps

### Step 1 — Record the chosen script version and image version in the specs

- `docs/agents/specs/docker/images.md`, [Deploy tooling](../../specs/docker/images.md#deploy-tooling): state that the image uses `darthjee/scripts:0.11.0`, because `0.12.0` (`cleanup`) is not released yet, and that moving to `0.12.0` is a version bump plus an image rebuild. State that the tooling ships starting with `circleci_pidgey-base:0.0.2`.
- `docs/agents/specs/docker/makefile.md`: in the Variables table, `CI_BASE_VERSION` becomes `0.0.2`; reword the `push-ci-base` row's "when the deploy tooling is added" note to say the tooling was added in `0.0.2`.
- Check `docs/agents/specs/deployment/release.md#deploy-script` for consistency (it already describes the `0.11.0` fallback); change it only if it contradicts the above.

## Files to Change

- `docs/agents/specs/docker/images.md` — script version actually used; first image version with the tooling.
- `docs/agents/specs/docker/makefile.md` — `CI_BASE_VERSION` value and `push-ci-base` note.

## Notes

- No new decisions: these updates only record the choices made in issue #98.
