# API Token Endpoints

Purpose: define the `/api-tokens*.json` endpoints: list, create (plain token returned once)
and revoke an organization's API tokens.

Shared rules (access, guards, `{id}` handling, field rules, operator log) are in
[pidgey-admin.md](../pidgey-admin.md). Token format, hashing, scopes and the checks on `/v1`
are in [authentication/api-tokens.md](../../authentication/api-tokens.md); the table in
[organizations/data-model/api-tokens.md](../../../organizations/data-model/api-tokens.md).
Errors use [conventions/errors.md](../../conventions/errors.md).

| Method & path | Success |
| --- | --- |
| `GET /api-tokens.json` | `200` |
| `POST /api-tokens.json` | `201` |
| `DELETE /api-tokens/{id}.json` | `204` |

These routes manage tokens with the **cookie session**; an API token never authenticates them
(see [disjoint credentials](../../authentication/api-tokens.md#disjoint-credentials)).

## API token item

```json
{
  "id": 5,
  "name": "Shop backend",
  "tokenPrefix": "pdg_",
  "scopes": ["send"],
  "createdAt": "2026-10-17T12:00:00.000Z",
  "lastUsedAt": "2026-10-20T09:30:00.000Z",
  "revokedAt": null
}
```

| Field | Content |
| --- | --- |
| `id` | `api_tokens.id`. |
| `name` | Label, as stored (after trimming). |
| `tokenPrefix` | `api_tokens.token_prefix`, the non-secret display prefix. |
| `scopes` | Array of scope names. |
| `createdAt` | ISO 8601 UTC with milliseconds. |
| `lastUsedAt` | Last successful use on `/v1`, or `null` if never used. |
| `revokedAt` | Revocation time, or `null` while the token is active. |

Never included: `token_hash`, `organizationId`, or the plain token (except `token` in the
create response, below).

## `GET /api-tokens.json`

Lists the organization's tokens, **including revoked ones** (with `revokedAt` set), ordered by
`id` ascending, not paginated.

Success: **`200`**, `{ "apiTokens": [<API token item>, …] }` (an empty array when there is
none).

## `POST /api-tokens.json`

Creates a token.

Request:

```json
{ "name": "Shop backend", "scopes": ["send"] }
```

| Field | Rules |
| --- | --- |
| `name` | **required**; string, not empty (after trimming), max 255 characters. Not unique |
| `scopes` | **required**; non-empty array of strings, each a known scope name (v1: only `send`) |

- An unknown scope name → `400 VALIDATION_FAILED`
  (`each value in scopes must be one of the following values: send`).
- An empty array → `400 VALIDATION_FAILED` (`scopes should not be empty`).
- **Duplicates are removed** before storing (`["send", "send"]` → `["send"]`).
- Two tokens may share a `name`.

Steps:

1. generate the plain token as in
   [authentication/api-tokens.md](../../authentication/api-tokens.md#format) (CSPRNG, `pdg_`
   prefix);
2. store its SHA-256 hex in `token_hash`, the display prefix in `token_prefix`, the
   organization from the session, `name` and the deduplicated `scopes`;
3. answer.

Success: **`201`**, the item plus `token`, the **plain token, returned once**:

```json
{
  "apiToken": {
    "id": 5,
    "name": "Shop backend",
    "tokenPrefix": "pdg_",
    "scopes": ["send"],
    "createdAt": "2026-10-17T12:00:00.000Z",
    "lastUsedAt": null,
    "revokedAt": null,
    "token": "pdg_…"
  }
}
```

- The plain token is **never** returned again (not by the list, not by any other route),
  never stored and never logged. A lost token cannot be recovered: revoke it and create a new
  one.
- One operator log line (`api-token-create`, the new id); never the plain token or its hash.

## `DELETE /api-tokens/{id}.json`

Revokes a token: sets `revoked_at` to now. The row is **kept** (it stays in the list with
`revokedAt`). No body.

Success: **`204`**, no body.

- A revoked token is rejected on `/v1` immediately (`401`, see
  [authentication/api-tokens.md](../../authentication/api-tokens.md#checks)).
- Revoking an **already revoked** token answers `204` again (idempotent) and keeps the
  original `revoked_at`.
- One operator log line (`api-token-revoke`, the id) when the token was active; none on an
  idempotent repeat.
- There is no un-revoke.

## Errors summary

| Case | Answer |
| --- | --- |
| Cross-site `POST` / `DELETE` | `403 FORBIDDEN` — `Cross-site request rejected` |
| Not authenticated, or user not `approved` | gate's `401` |
| Malformed `{id}` | `400 BAD_REQUEST` — `Validation failed (numeric string is expected)` |
| Validation failure | `400 VALIDATION_FAILED` |
| Unknown, out of range or foreign `{id}` | `404 NOT_FOUND` — `API token not found` |

## Edge cases

- `scopes` empty, containing an unknown name, or with duplicates → `400`, `400`,
  deduplicated.
- `scopes` not an array (e.g. `"send"`) → `400 VALIDATION_FAILED`.
- `scopes: ["admin"]` (the removed scope) → `400 VALIDATION_FAILED`.
- A whitespace-only `name` → `400 VALIDATION_FAILED`.
- Revoking another organization's token → `404`, same body as unknown.
- Revoking an already revoked token → `204`.
- A token of an organization with no `approved` user is created normally (the creator is
  approved), but it is refused on `/v1` whenever the organization has no approved user (see
  [authentication/api-tokens.md](../../authentication/api-tokens.md#checks)).

## Testable rules

The implementation must cover at least:

- the item shape; `token_hash` never exposed; the plain token only in the create response,
  and its SHA-256 equals the stored `token_hash`;
- the gate's `401` for anonymous calls, before validation; no admin check;
- organization isolation: `404` on revoke for another organization's token; the list shows
  only the organization's tokens;
- `name` and `scopes` rules, including deduplication and unknown scopes;
- the list includes revoked tokens; revoke sets `revoked_at`, keeps the row and is idempotent;
- a revoked token is refused on `POST /v1/messages`;
- operator log lines on create and revoke, never containing the plain token or its hash.

## Pending decisions

None here. Token expiration and finer-grained scopes are pending in
[authentication/api-tokens.md](../../authentication/api-tokens.md#pending-decisions).
