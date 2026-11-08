# Compose and .env

`docker-compose.yml`:

- `mailpit`: set `image: axllent/mailpit:v1.32.0`. Nothing else changes (ports, use).
- Add `mailpit-plain`, `mailpit-starttls` and `mailpit-ssl` with the same image and the
  env from the [instance table](../plan.md#instances). They publish no host ports.
  - The two TLS services mount `./source/tests/fixtures/tls:/fixtures/tls:ro`.
  - They set `MP_SMTP_TLS_CERT=/fixtures/tls/server.pem` and
    `MP_SMTP_TLS_KEY=/fixtures/tls/server-key.pem`.
- `tests`:
  - `depends_on`: replace `mailpit` with the three test services, keeping `base_build` and
    `mysql`;
  - `environment`: add the compose column of the
    [env key table](../plan.md#test-environment-keys) (`SMTP_ENCRYPTION_KEY` already comes
    from `.env`).
- `make dev-up` keeps starting only the dev `mailpit`.

`.env`:

- Remove the `# Application SMTP (mailpit)` block (`SMTP_HOST`, `SMTP_PORT`).
- Add `SMTP_ALLOW_PRIVATE_HOSTS=true`, with a comment saying it lets the development app
  reach the private-network dev `mailpit`.

## Files to Change

- `docker-compose.yml` — pinned tag, three test services, `tests` dependencies and env.
- `.env` — drop `SMTP_HOST` / `SMTP_PORT`, add `SMTP_ALLOW_PRIVATE_HOSTS=true`.
