# Add the integration app service

Add a compose service `integration_app` that runs the Pidgey backend for the client integration spec, isolated from the development data.

- `<<: *base` (same `darthjee/dev_pidgey` image, `.env`, `source/` and vendor mounts), `command: apache2-foreground`, **no** published port.
- `environment`: `PHP_ENV: development`, `DB_NAME: pidgey_integration`, `USER_APPROVAL_REQUIRED: "false"` (a registered user is approved at once, so the seed needs no admin), `SMTP_ALLOW_PRIVATE_HOSTS: "true"` (reach `mailpit-plain`).
- `depends_on`: `base_build`, `mysql`, `mailpit-plain`.
- Mention `make clients-integration` in a comment, like the `clients` service does.

## Files to Change
- `docker-compose.yml` — new `integration_app` service.
