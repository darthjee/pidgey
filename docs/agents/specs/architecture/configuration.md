# Configuration

Purpose: list every environment variable the application reads, with its default and effect.

## Rules

- Configuration comes from environment variables, read through `Pidgey\Infra\Env`.
  Development values live in the `.env` file used by docker-compose (see
  [docker/compose.md](../docker/compose.md)).
- Names are **bare** (no application prefix), matching the existing `DB_*` and `SMTP_*`
  variables.
- Durations are integers in **seconds**. A duration must be a **positive integer**; any other
  value (zero, negative, non-numeric) refuses boot (or the command exits non-zero).
- Counts (e.g. `ACCOUNT_EDIT_MAX_ATTEMPTS`) must be **positive integers**; any other value
  refuses boot (or the command exits non-zero). **Exception:** `TRUSTED_PROXY_HOPS` must be a
  **non-negative** integer (`0` is allowed); any other value refuses boot.
- Booleans are enabled only by the exact value `true`; anything else is `false`.
  **Exception:** `USER_APPROVAL_REQUIRED` is disabled only by the exact value `false`; anything
  else (unset, empty, a typo) keeps approval required, so a typo never silently opens
  registration.
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

Used by [api/authentication.md](../api/authentication.md) and, for the `AUTHORIZATION_REQUEST_*`
settings, by [api/endpoints/auth/device-authorization.md](../api/endpoints/auth/device-authorization.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `SECRET_KEY` | required | Signs access-token JWTs (HS256). See [Secret keys](#secret-keys). |
| `PREVIOUS_SECRET_KEYS` | empty | Comma-separated retired keys, used **only to verify** access tokens during a key rotation (see [access-token.md](../api/authentication/access-token.md#key-rotation) and [Secret keys](#secret-keys)). |
| `ACCESS_TOKEN_TTL` | `900` | JWT `exp` and the `access_token` cookie `Max-Age`, in seconds. Must be less than `REFRESH_TOKEN_TTL`, otherwise boot is refused. |
| `REFRESH_TOKEN_TTL` | `604800` (7 days) | Refresh-token lifetime, in seconds (see [sessions.md](../api/authentication/sessions.md)). |
| `USER_APPROVAL_REQUIRED` | `true` | New registrations start `pending` unless the value is exactly `false` (the exception to the boolean rule, see [Rules](#rules)); then they start `approved` (see [gate.md](../api/authentication/gate.md#user-status-at-the-gate)). Defaults to `true` because open registration lets a stranger store SMTP connections and make the server open TCP connections to arbitrary hosts and ports (test email, `POST /v1/messages`): an SSRF-style probe of the internal network. Openness is opt-in; the development `.env` may set `false`. |
| `TRUSTED_PROXY_HOPS` | `1` | How many `X-Forwarded-For` hops to trust when resolving the client IP (a non-negative count, see [Rules](#rules); resolution in [client-ip.md](../api/conventions/client-ip.md)). Set it to `0` when no reverse proxy sits in front of the app; otherwise clients can spoof their IP through `X-Forwarded-For`. |
| `AUTHORIZATION_REQUEST_TTL` | `3600` (1 h) | Lifetime of a device request, in seconds: `expires_at = now + TTL` (see [requester.md](../api/endpoints/auth/device-authorization/requester.md#post-authauthorization-requestsjson)). |
| `AUTHORIZATION_REQUEST_CREATE_LIMIT` | `5` | Creates allowed per client IP and, separately, per username within the window (a count, see [Rules](#rules)). |
| `AUTHORIZATION_REQUEST_CREATE_WINDOW` | `60` | Rate-limit window of the create endpoint, in seconds. |
| `AUTHORIZATION_REQUEST_MAX_OPEN_PER_USER` | `5` | Open requests per user; the oldest is expired to make room (a count). |
| `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS` | `5` | Wrong passwords before the per-request authorize lockout (a count, see [approver.md](../api/endpoints/auth/device-authorization/approver.md#lockout)). |
| `AUTHORIZATION_REQUEST_AUTHORIZE_LOCK_TTL` | `300` (5 min) | Per-request lockout duration, in seconds: `authorize_locked_until = now + TTL`. |

### Origins

Used by [api/conventions/origin-check.md](../api/conventions/origin-check.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `ALLOWED_ORIGINS` | empty | Comma-separated bare origins (e.g. `https://admin.example.com`) trusted by the origin check and CORS; exact match only. |
| `FRONTEND_BASE_URL` | empty | Fallback trusted origin when `ALLOWED_ORIGINS` is empty; base URL of links in emails. When empty, system emails (password recovery) are not sent, since their link would be broken (see [recovery.md](../api/endpoints/auth/recovery.md#recovery-email)). |

### Password recovery and account edit

Used by [api/endpoints/auth/recovery.md](../api/endpoints/auth/recovery.md) and
[api/endpoints/auth/account.md](../api/endpoints/auth/account.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `PASSWORD_RESET_TOKEN_TTL` | `1800` (30 min) | Password-reset token lifetime, in seconds: `expires_at = created_at + TTL` (see [recovery.md](../api/endpoints/auth/recovery.md#password-reset-tokens)). |
| `PASSWORD_RESET_COOLDOWN` | `60` | Seconds after a user's last password-reset token during which recover creates no new token and sends no email (see [recovery.md](../api/endpoints/auth/recovery.md#post-authrecoverjson)). |
| `ACCOUNT_EDIT_MAX_ATTEMPTS` | `5` | Failed `PATCH /auth/account.json` attempts before locking (a count, see [Rules](#rules); see [account.md](../api/endpoints/auth/account.md#lockout)). |
| `ACCOUNT_EDIT_LOCK_TTL` | `300` (5 min) | Lock duration, in seconds: `locked_until = now + TTL` (see [account.md](../api/endpoints/auth/account.md#lockout)). |

There is no mail-enabled flag and there are no `SYSTEM_SMTP_*` settings: system emails are
sent through the system organization's active SMTP connection (see
[recovery.md](../api/endpoints/auth/recovery.md#recovery-email)).

## Secret keys

- A key is the **raw environment string**, used as is; its length is measured in **bytes**.
- The app **refuses to boot** when `SECRET_KEY` is missing or shorter than **32 bytes**.
- Generate keys from **at least 32 CSPRNG bytes**, hex- or base64url-encoded (e.g.
  `openssl rand -hex 32`).
- Keys must not contain `,` (the `PREVIOUS_SECRET_KEYS` separator).
- `PREVIOUS_SECRET_KEYS` is split on `,` and each entry has its surrounding whitespace
  trimmed. An **empty entry** (e.g. a trailing or doubled comma) refuses boot, and every entry
  follows the same length rule as `SECRET_KEY`; an invalid entry also refuses boot.
- Keys are never logged or returned by any endpoint.
- `.env` ships a **clearly fake development key** (recognizable as such, never usable in
  production). Production keys are generated with a CSPRNG and injected by the deployment.

## Pending decisions

- **Pending decision:** the log-level variable (name and default); logging is configured
  through Monolog but no variable is read yet.
- **Pending decision:** refusing to boot when `SECRET_KEY`, or any `PREVIOUS_SECRET_KEYS`
  entry, equals the development key shipped in `.env` while `PHP_ENV` is neither
  `development` nor `test`.
