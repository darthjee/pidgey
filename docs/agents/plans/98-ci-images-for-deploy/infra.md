# Infra Plan: CI images for deploy

Main plan: [plan.md](plan.md)

## Shared contracts

- Produce `darthjee/circleci_pidgey-base:0.0.2` (`CI_BASE_VERSION=0.0.2`).
- `deploy_frontend.sh` from `darthjee/scripts:0.11.0` at `/usr/local/sbin/deploy_frontend.sh`.
- `openssh-client` and `rsync` installed; `bash` already present in `cimg/php`.
- Do not touch `.circleci/config.yml` (sub-issue 4) or `docs/agents/specs/` (product-owner).

## Implementation Steps

### Step 1 — Add deploy tooling to the CI image

In `dockerfiles/circleci_pidgey-base/Dockerfile`:

- Declare the script version once, as a top-level `ARG SCRIPTS_VERSION=0.11.0` and a named stage `FROM darthjee/scripts:${SCRIPTS_VERSION} AS scripts`, before `FROM cimg/php:8.4` (same stage pattern as `dockerfiles/vite_pidgey-base/Dockerfile`). This keeps the version a single value to bump for `0.12.0`.
- Add `openssh-client` and `rsync` to the existing `apt-get install` list in the root `RUN` layer.
- While still `USER root`, `COPY --from=scripts /home/scripts/sbin/deploy_frontend.sh /usr/local/sbin/deploy_frontend.sh` and make sure it is executable (`chmod 0755` if the copied mode isn't).
- Update the comments to say the image also carries the deploy tooling.

### Step 2 — Bump the image version

In `Makefile`, set `CI_BASE_VERSION=0.0.2`. Leave `build-ci-base` / `push-ci-base` unchanged.

## Files to Change

- `dockerfiles/circleci_pidgey-base/Dockerfile` — scripts stage, `openssh-client`, `rsync`, `deploy_frontend.sh`.
- `Makefile` — `CI_BASE_VERSION` from `0.0.1` to `0.0.2`.

## CI Checks

No CI job builds this image. Verify locally:

- `make build-ci-base`
- `docker run --rm --platform linux/amd64 darthjee/circleci_pidgey-base:0.0.2 sh -c 'command -v bash ssh rsync deploy_frontend.sh && php -v && composer --version'`

## Notes

- The image is pushed by hand (`make push-ci-base`) by the owner after merge; it must be published before sub-issue 4 uses it. The implementer does not push.
- CI keeps using `0.0.1` until sub-issue 4 changes `.circleci/config.yml`.
