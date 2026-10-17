# Authentication

Purpose: define how API clients and the admin UI authenticate and are authorized.

## API tokens

- Sent as `Authorization: Bearer <token>`.
- Generated with a CSPRNG (`random_bytes`, ≥ 32 bytes), encoded, with a short
  non-secret prefix (e.g. `pdg_`) to help identification.
- **Hashed at rest** (SHA-256); the plain token is shown only once at creation.
- Belong to an organization; created and revoked in the admin UI.
- Each token carries a set of **scopes**:

| Scope | Grants |
| --- | --- |
| `send` | `POST /v1/messages`. |
| `admin` | Admin endpoints (organization, SMTP connections, tokens). |

- **Revocation:** sets `revoked_at`; revoked tokens are rejected immediately (`401`).
- `last_used_at` is updated on successful use.
- Missing/invalid/revoked token → `401`; valid token lacking scope → `403`.

## Admin UI session

- Classic PHP session cookie (see [organizations/bootstrap.md](../organizations/bootstrap.md)).
- A logged-in user has full access to their organization (equal access policy).

**Pending decisions:** final scope list (finer-grained scopes such as
`smtp:write`, `tokens:write`); token expiration.
