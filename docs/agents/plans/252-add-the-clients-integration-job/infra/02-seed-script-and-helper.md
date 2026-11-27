# Write the seed script and the shared spec helper

**`clients/scripts/integration_seed.mjs`** (Node, global `fetch`, no dependencies). Argument: the output file path. Base URL from `PIDGEY_INTEGRATION_URL`.

1. `POST /auth/register.json` for organization A (`username`/`email` with a random suffix, `password`, `organizationName`); keep the `access_token` value from `Set-Cookie` and send it back as `Cookie: access_token=…` on the next calls.
2. `POST /smtp-connections.json` `{ name: "Mailpit", host: "mailpit-plain", port: 2025, encryption: "none" }` (fields per `docs/agents/api/endpoints/pidgey-admin/smtp-connections.md`), then `POST /smtp-connections/:id/activate.json`.
3. `POST /api-tokens.json` `{ name, scopes: ["send"] }` twice; `DELETE /api-tokens/:id.json` the second one.
4. Register organization B and create one `send` token; no SMTP connection.
5. Write `PIDGEY_INTEGRATION_TOKEN`, `PIDGEY_INTEGRATION_REVOKED_TOKEN`, `PIDGEY_INTEGRATION_NO_CONNECTION_TOKEN` as `KEY=value` lines to the output file (mode `0600`).

Any non-2xx answer exits non-zero with the method, path, status and envelope `code`, never a token or a cookie.

**`clients/scripts/integration_support.mjs`**: the helper of [plan.md](../plan.md#shared-helper-clientsscriptsintegration_supportmjs-infra-produces-both-specs-import-by-relative-path) (`integrationEnv`, `uniqueSubject`, `findMessages`, `waitForMessage`), with JSDoc on every export.

## Files to Change
- `clients/scripts/integration_seed.mjs` — new seed script.
- `clients/scripts/integration_support.mjs` — new shared helper for the two specs.
