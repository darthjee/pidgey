# Configuration

Purpose: list every environment variable the application reads, with its default and effect.

## Rules

- Configuration comes from environment variables, read through `Pidgey\Infra\Env`.
  Development values live in the `.env` file used by docker-compose (see
  [docker/compose.md](../docker/compose.md)).
- Names are **bare** (no application prefix), matching the existing `DB_*` and `SMTP_*`
  variables.
- Durations are integers in **seconds**.
- Booleans are enabled only by the exact value `true`; anything else is `false`.
- "required" means the app refuses to start (or the command exits non-zero) when the value is
  missing.

## Settings

### Runtime and database

| Setting | Default | Effect |
| --- | --- | --- |
| `PHP_ENV` | none | Runtime environment (`development`, `test`, `production`); set by docker-compose. |
| `DB_HOST` | required | MySQL host. |
| `DB_PORT` | `3306` | MySQL port. |
| `DB_NAME` | required | Database name. |
| `DB_USER` | required | Database user. |
| `DB_PASSWORD` | empty | Database password. |
| `DB_WAIT_TIMEOUT` | `60` | Seconds the database CLI waits for MySQL to accept connections. |
| `MIGRATE_LOCK_TIMEOUT` | `60` | Seconds a migration run waits for the migration lock (see [database.md](database.md)). |

### SMTP (development and tests)

| Setting | Default | Effect |
| --- | --- | --- |
| `SMTP_HOST` | none | SMTP host used by the development and test setup (Mailpit). Organizations' own SMTP connections are stored in the database, not configured here (see [email/smtp-connections.md](../email/smtp-connections.md)). |
| `SMTP_PORT` | none | SMTP port of the development and test setup (Mailpit: `1025`). |

### Authentication

Used by [api/authentication.md](../api/authentication.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `SECRET_KEY` | required | Signs access-token JWTs (HS256). See [Secret keys](#secret-keys). |
| `PREVIOUS_SECRET_KEYS` | empty | Comma-separated retired keys, used **only to verify** access tokens during a key rotation (see [access-token.md](../api/authentication/access-token.md#key-rotation)). |
| `ACCESS_TOKEN_TTL` | `900` | JWT `exp` and the `access_token` cookie `Max-Age`, in seconds. |
| `REFRESH_TOKEN_TTL` | `604800` (7 days) | Refresh-token lifetime, in seconds (see [sessions.md](../api/authentication/sessions.md)). |
| `USER_APPROVAL_REQUIRED` | `false` | Exactly `true` → new registrations start `pending` (see [gate.md](../api/authentication/gate.md#user-status-at-the-gate)). |
| `TRUSTED_PROXY_HOPS` | `1` | How many `X-Forwarded-For` hops to trust when resolving the client IP. |

### Origins

Used by [api/conventions/origin-check.md](../api/conventions/origin-check.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `ALLOWED_ORIGINS` | empty | Comma-separated bare origins (e.g. `https://admin.example.com`) trusted by the origin check and CORS; exact match only. |
| `FRONTEND_BASE_URL` | empty | Fallback trusted origin when `ALLOWED_ORIGINS` is empty; base URL of links in emails. |

Settings for password recovery (#28), device authorization and account-edit lockout (#29)
are added to this table by those specs.

## Secret keys

- The app **refuses to boot** when `SECRET_KEY` is missing or shorter than **32 bytes**.
- Every `PREVIOUS_SECRET_KEYS` entry follows the same rule; an invalid entry also refuses
  boot.
- Keys are never logged or returned by any endpoint.
- `.env` ships a **clearly fake development key** (recognizable as such, never usable in
  production). Production keys are generated with a CSPRNG and injected by the deployment.

## Pending decisions

- **Pending decision:** the log-level variable (name and default); logging is configured
  through Monolog but no variable is read yet.
