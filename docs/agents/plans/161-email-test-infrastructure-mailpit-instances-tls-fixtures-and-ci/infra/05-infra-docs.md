# Infra docs

Make the infra docs match the implementation, replacing every "inline PEM, or a small test
image" alternative with the CI-binary decision:

- `docs/agents/deployment/pipeline.md`:
  - the `backend_tests` row: `mysql:9` service container, Mailpit binary from the base image;
  - "Database access in CI": drop `SMTP_HOST` / `SMTP_PORT`;
  - turn "Target state for the email tests" into the current state: instances started in
    the background on `localhost` with the fixture files from the checkout, the wait step,
    the CI key values and the reason (Mailpit reads TLS files only by path; service
    containers cannot mount files).
- `docs/agents/specs/docker/compose.md`:
  - the pinned tag `v1.32.0`;
  - the `/fixtures/tls` mount and the `server.pem` / `server-key.pem` paths;
  - the `tests` environment.
- `docs/agents/specs/docker/images.md` and `docs/agents/specs/docker/makefile.md`:
  `circleci_pidgey-base` `0.0.4` ships the Mailpit `v1.32.0` binary; update
  `CI_BASE_VERSION`.
- `docs/agents/specs/architecture/configuration.md`: confirm the `SMTP_HOST` / `SMTP_PORT`
  rows are gone, and that `TEST_MAILPIT_*` is documented with the compose and CI values.

## Files to Change

- `docs/agents/deployment/pipeline.md`
- `docs/agents/specs/docker/compose.md`
- `docs/agents/specs/docker/images.md`
- `docs/agents/specs/docker/makefile.md`
- `docs/agents/specs/architecture/configuration.md` (only if still out of date)
