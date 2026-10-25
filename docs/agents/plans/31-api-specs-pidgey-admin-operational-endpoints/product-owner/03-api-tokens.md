# API token endpoints

Create `api/endpoints/pidgey-admin/api-tokens.md` (issue Solution, section 4):

- the item shape `{ id, name, tokenPrefix, scopes, createdAt, lastUsedAt, revokedAt }`;
- `GET /api-tokens.json` → `200 { apiTokens }`, including revoked tokens;
- `POST /api-tokens.json`, body `{ name, scopes }`: `name` 1–255 characters, not unique;
  `scopes` required non-empty array of known scopes (v1: `send`), unknown → `400`,
  duplicates removed → `201` with the item plus `token`, the plain token returned **once**,
  never logged or returned again (generation and hashing as in
  `api/authentication/api-tokens.md`);
- `DELETE /api-tokens/{id}.json` → sets `revoked_at`, row kept, `204`; idempotent on an
  already revoked token;
- operator log on create and revoke (never the plain token or hash);
- edge cases and testable rules.

## Files to Change
- `docs/agents/specs/api/endpoints/pidgey-admin/api-tokens.md` — new.
