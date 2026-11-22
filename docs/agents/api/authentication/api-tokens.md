# API Tokens

Tokens are stored in the `api_tokens` table
([organizations/data-model/api-tokens.md](../../organizations/data-model/api-tokens.md)).
They are created, listed and revoked from the admin UI, through the cookie-authenticated
endpoints of [endpoints/pidgey-admin/api-tokens.md](../endpoints/pidgey-admin/api-tokens.md).

## Format

- Sent as `Authorization: Bearer <token>`.
- Generated with a CSPRNG (`random_bytes`, at least 32 bytes), encoded, with a short
  non-secret prefix `pdg_` to help identification. `api_tokens.token_prefix` stores **only**
  the non-secret display prefix, never any part of the secret.
- **Hashed at rest** with SHA-256 (`api_tokens.token_hash`); the plain token is shown only
  once, at creation. The plain token is never stored or logged.
- Belongs to an organization; a request authenticated by a token acts within that
  organization ([organizations/scope.md](../../organizations/scope.md)).

## Scopes

`api_tokens.scopes` is a JSON array of scope names. In v1 there is **one** scope:

| Scope | Grants |
| --- | --- |
| `send` | `POST /v1/messages`. |

The former `admin` scope is **removed**: admin endpoints are reachable only with the cookie
session. The column stays a JSON array so scopes can be added later.

## Checks

- Missing, malformed, unknown or revoked token → `401` (`UNAUTHORIZED`).
- Token of an organization with **no `approved` user** (every user `pending` or `denied`) →
  the **same** `401` as a revoked token. No data changes (`revoked_at` stays unset): the
  token works again as soon as a user of the organization is approved (see
  [endpoints/sending.md](../endpoints/sending.md#token-authentication)).
- Valid token lacking the route's scope → `403` (`FORBIDDEN`).
- Only the scope check applies on `/v1`: there is no gate and no admin check. The
  [origin check](../conventions/origin-check.md) still runs on state-changing methods; server
  callers send no `Origin` and pass it.
- **Revocation** sets `revoked_at`; a revoked token is rejected immediately.
- `last_used_at` is updated on a successful authentication, including when the scope check
  then answers `403`; it is never updated for a `401`.
- Revoking an already revoked token is idempotent (see
  [endpoints/pidgey-admin/api-tokens.md](../endpoints/pidgey-admin/api-tokens.md#delete-api-tokensidjson)).

Errors use the envelope of [conventions/errors.md](../conventions/errors.md).

## Disjoint credentials

The two credentials never overlap:

| Surface | Credential | Wrong credential presented |
| --- | --- | --- |
| Admin UI: `/auth/*.json`, `/admin/users/*.json`, organization, SMTP connections, API tokens, test email | Cookie session ([gate.md](gate.md)) | A Bearer header is ignored; without a valid cookie the gate answers `401`. |
| Public API: `/v1/*` (`POST /v1/messages`) | Bearer API token | An `access_token` cookie is ignored; without a Bearer token the answer is `401`. |

### Public exception

The **only public `/v1` route** is `GET` and `HEAD` on exactly `/v1/openapi.json`, the OpenAPI
document ([endpoints/openapi.md](../endpoints/openapi.md)). It is a route registered without
`ApiTokenAuth`, **not** a prefix exception: the match is exact, so no other `/v1` path or
method becomes public (`/v1/openapi.json/`, `/v1/openapi.jsonx` and `POST /v1/openapi.json`
still get the routing `404`). Credentials are ignored there: a Bearer header, valid or not,
and a cookie are neither checked nor rejected, and the document is served.

## Pending decisions

- **Pending decision:** finer-grained scopes (e.g. per SMTP connection or per sender), if any.
- **Pending decision:** token expiration (an optional `expires_at`).
