# Add oasdiff to the CI image

Add oasdiff to the CI image, the same way Mailpit is added.

1. In `dockerfiles/circleci_pidgey-base/Dockerfile`:
   - Add `ARG OASDIFF_VERSION=<tag>` next to `MAILPIT_VERSION`, with a comment saying it is bumped together with `OASDIFF_VERSION` in the `Makefile`.
   - Add a stage, `FROM tufin/oasdiff:${OASDIFF_VERSION} AS oasdiff`.
   - In the final stage, `COPY --from=oasdiff <binary path> /usr/local/bin/oasdiff` and `chmod 0755`. Check the binary's path inside the image first (e.g. `docker run --rm --entrypoint sh tufin/oasdiff:<tag> -c 'command -v oasdiff'`, or `docker image inspect` for the entrypoint).
   - The binary must run on `cimg/php` (glibc, amd64). oasdiff release binaries are static Go builds. Confirm with `docker run --rm --platform linux/amd64 <built image> oasdiff --version`.
2. In the `Makefile`:
   - Add `OASDIFF_VERSION=<tag>` and set `CI_BASE_VERSION=0.0.5`.
   - `build-ci-base` passes `--build-arg OASDIFF_VERSION=$(OASDIFF_VERSION)`.
3. Build the image locally with `make build-ci-base` to prove the Dockerfile works. Do **not** push it: pushing is the maintainer's manual step.

## Files to Change
- `dockerfiles/circleci_pidgey-base/Dockerfile`: add the `OASDIFF_VERSION` arg, the `oasdiff` stage, and the binary copy.
- `Makefile`: add `OASDIFF_VERSION`, set `CI_BASE_VERSION=0.0.5`, and pass the build arg in `build-ci-base`.
