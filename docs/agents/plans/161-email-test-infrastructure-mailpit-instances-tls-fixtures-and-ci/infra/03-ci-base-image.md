# CI base image

In `dockerfiles/circleci_pidgey-base/Dockerfile`:

- add a stage `FROM axllent/mailpit:v1.32.0 AS mailpit` next to the `scripts` stage, with an
  `ARG MAILPIT_VERSION=v1.32.0` and a comment saying to bump it together with compose;
- copy the binary as root: `COPY --from=mailpit /mailpit /usr/local/bin/mailpit`, then
  `chmod 0755`.

In the `Makefile`, bump `CI_BASE_VERSION` from `0.0.3` to `0.0.4`.

Verify, if Docker is available, that `make build-ci-base` builds and that `docker run --rm
--entrypoint mailpit darthjee/circleci_pidgey-base:0.0.4 version` prints `v1.32.0`. Do not
push.

## Files to Change

- `dockerfiles/circleci_pidgey-base/Dockerfile` — Mailpit stage and binary copy.
- `Makefile` — `CI_BASE_VERSION=0.0.4`.
