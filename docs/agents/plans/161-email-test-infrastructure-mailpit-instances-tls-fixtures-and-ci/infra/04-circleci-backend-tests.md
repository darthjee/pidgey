# CircleCI backend_tests

In `.circleci/config.yml`:

- `ci_base_image`: `darthjee/circleci_pidgey-base:0.0.4`.
- `backend_tests`:
  - remove the `axllent/mailpit` service container (`mysql:9` stays);
  - primary env: remove `SMTP_HOST` / `SMTP_PORT`, and add the CI column of the
    [env key table](../plan.md#test-environment-keys);
  - after `composer_setup`, add three `background: true` run steps, one per instance. Each
    runs `mailpit` with the env from the [instance table](../plan.md#instances). The TLS
    ones use `MP_SMTP_TLS_CERT=tests/fixtures/tls/server.pem` and
    `MP_SMTP_TLS_KEY=tests/fixtures/tls/server-key.pem`, which are relative to the flattened
    checkout;
  - then a `Wait for Mailpit` step that polls `http://localhost:<apiPort>/api/v1/info` for
    each instance with `curl -fsS`, up to 30 seconds, and exits non-zero naming the instance
    that never answered;
  - then the existing `Prepare database` and `Tests` steps.

## Files to Change

- `.circleci/config.yml` — base image tag, `backend_tests` env, Mailpit start and wait
  steps, removed service container.
