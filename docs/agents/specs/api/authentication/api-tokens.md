# API Tokens

Purpose: define the Bearer API tokens that authenticate the public `/v1` sending API, their
scopes and revocation.

Tokens are stored in the `api_tokens` table
([organizations/data-model/api-tokens.md](../../organizations/data-model/api-tokens.md)).
They are created, listed and revoked from the admin UI (endpoints specified in #31).

## Format

- Sent as `Authorization: Bearer <token>`.
- Generated with a CSPRNG (`random_bytes`, at least 32 bytes), encoded, with a short
  non-secret prefix `pdg_` to help identification (`api_tokens.token_prefix`).
- **Hashed at rest** with SHA-256 (`api_tokens.token_hash`); the plain token is shown only
  once, at creation.
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
- Valid token lacking the route's scope → `403` (`FORBIDDEN`).
- Only the scope check applies on `/v1`: there is no gate and no admin check. The
  [origin check](../conventions/origin-check.md) still runs on state-changing methods; server
  callers send no `Origin` and pass it.
- **Revocation** sets `revoked_at`; a revoked token is rejected immediately.
- `last_used_at` is updated on successful use.

Errors use the envelope of [conventions/errors.md](../conventions/errors.md).

## Disjoint credentials

The two credentials never overlap:

| Surface | Credential | Wrong credential presented |
| --- | --- | --- |
| Admin UI: `/auth/*.json`, `/admin/users/*.json`, organization, SMTP connections, API tokens, test email | Cookie session ([gate.md](gate.md)) | A Bearer header is ignored; without a valid cookie the gate answers `401`. |
| Public API: `/v1/*` (`POST /v1/messages`) | Bearer API token | An `access_token` cookie is ignored; without a Bearer token the answer is `401`. |

## Pending decisions

- **Pending decision:** finer-grained scopes (e.g. per SMTP connection or per sender), if any.
- **Pending decision:** token expiration (an optional `expires_at`).
