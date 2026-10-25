# Authentication

Purpose: index of the specs defining how the admin UI and API clients authenticate and are
authorized (the Citadel contract, adapted to Pidgey).

Pidgey has two **disjoint** credentials:

- a **cookie session** (a JWT access token in the `access_token` cookie plus a refresh token)
  for the admin UI surface: `/auth/*.json`, `/admin/users/*.json` and Pidgey's organization,
  SMTP connection, API token and test-email endpoints;
- a **Bearer API token** with scopes for the public sending API (`POST /v1/messages`).

There is no PHP session. Endpoint payloads and error codes live in the endpoint specs
([endpoints.md](endpoints.md)); the settings used here are in
[architecture/configuration.md](../architecture/configuration.md).

Request logging must never record the `Authorization` header, the `Cookie` header, or
`refreshToken`/`password` body fields.

| File | Content |
| --- | --- |
| [access-token.md](authentication/access-token.md) | JWT HS256 claims, strict verification rules, `access_token` cookie, key rotation, why hand-rolled. |
| [sessions.md](authentication/sessions.md) | Refresh tokens, sessions, atomic rotation, revocation reasons, replay detection, clock rules. |
| [gate.md](authentication/gate.md) | Guard order, the per-request gate, uniform `401`, admin check, user status at the gate, shared `user` payload. |
| [api-tokens.md](authentication/api-tokens.md) | Bearer API tokens for `/v1`: format, the `send` scope, revocation, disjoint credentials. |
| [local-development.md](authentication/local-development.md) | Using the `Secure` cookie in development without TLS; production HTTPS. |

## Pending decisions

Each detail file lists its own. Summary:

- Finer-grained Bearer scopes and token expiration ([api-tokens.md](authentication/api-tokens.md)).
- Cleanup of `refresh_tokens` ([sessions.md](authentication/sessions.md)).
- Refresh-token storage in the browser (XSS exposure, revisited with #32)
  ([sessions.md](authentication/sessions.md)).
- An absolute session lifetime checked against `started_at` (e.g. `SESSION_MAX_AGE`)
  ([sessions.md](authentication/sessions.md)).
- Renaming the cookie to `__Host-access_token` ([access-token.md](authentication/access-token.md)).
- Refusing to boot with the shipped development key outside development/test
  ([architecture/configuration.md](../architecture/configuration.md)).
