# Issue: CI images for deploy

## Description

Part of epic #95. The deploy context jobs (`upload_backend`, `upload_frontend`, `release`) call the shared `deploy_frontend.sh` from the `darthjee/scripts` image, which needs `bash`, `ssh` and `rsync`. Add this deploy tooling to the `circleci_pidgey-base` CI image, following the specs from sub-issue 1 (#96): [docker/images.md — Deploy tooling](../specs/docker/images.md#deploy-tooling).

## Problem

`circleci_pidgey-base:0.0.1` has PHP 8.4 and Composer but no `deploy_frontend.sh`, `ssh` or `rsync`, so the deploy jobs planned in sub-issue 4 cannot run in it.

## Expected Behavior

- `dockerfiles/circleci_pidgey-base/Dockerfile`:
  - copies the script in, not vendored: `COPY --from=darthjee/scripts:0.11.0 /home/scripts/sbin/deploy_frontend.sh /usr/local/sbin/` (the script version is a single, easy-to-bump value);
  - installs `openssh-client` and `rsync` in the existing `apt-get` layer (`bash` already ships with `cimg/php`).
- **Script version: `0.11.0`.** `0.12.0` (which adds `cleanup`) is not on Docker Hub yet; moving to it later is a follow-up (version bump and rebuild).
- **No frontend deploy image:** per the spec, `build_frontend` runs in `darthjee/circleci_node:0.2.1` and `upload_frontend` runs in `circleci_pidgey-base`, uploading the built `dist/` from the workspace, so it needs no node or yarn.
- `Makefile`: `CI_BASE_VERSION` bumped from `0.0.1` to `0.0.2`.
- Specs updated to match: `docs/agents/specs/docker/images.md` (the version actually used) and `docs/agents/specs/docker/makefile.md` (the `CI_BASE_VERSION` value).

## Solution

- Owner: `infra` (Dockerfiles, Makefile).
- Images are built and pushed by hand (`make build-ci-base && make push-ci-base`, see `docker/makefile.md`). `circleci_pidgey-base:0.0.2` must be published before sub-issue 4 uses it.
- Depends on sub-issue 1 (#96, merged).
- Out of scope: `.circleci/config.yml`. Moving the jobs to `circleci_pidgey-base:0.0.2` and adding the deploy jobs belongs to sub-issue 4.
