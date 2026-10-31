# Bump the CI image to scripts 0.12.0
Point the CI base image at `darthjee/scripts:0.12.0` so `deploy_frontend.sh cleanup` is available, and release it under a new tag.

1. In the Dockerfile, change `ARG SCRIPTS_VERSION=0.11.0` to `0.12.0`.
2. In the Makefile, bump `CI_BASE_VERSION` from `0.0.2` to `0.0.3`.
3. Run `make build-ci-base`, check that `deploy_frontend.sh` inside the image lists `cleanup` in its usage `Actions:`, then run `make push-ci-base`.
4. In `.circleci/config.yml`, change the `ci_base_image` anchor to `darthjee/circleci_pidgey-base:0.0.3`.

## Files to Change
- `dockerfiles/circleci_pidgey-base/Dockerfile` — `SCRIPTS_VERSION` becomes `0.12.0`.
- `Makefile` — `CI_BASE_VERSION=0.0.3`.
- `.circleci/config.yml` — the `ci_base_image` anchor points at `0.0.3`.
