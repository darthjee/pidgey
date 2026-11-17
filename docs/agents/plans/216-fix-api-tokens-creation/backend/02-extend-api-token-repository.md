# Extend ApiTokenRepository with list and revoke

Add the two organization-scoped methods the domain class needs; existing methods stay unchanged.

- `listByOrganization(int $organizationId): array` (`@return list<ApiToken>`): `SELECT <COLUMNS> FROM api_tokens WHERE organization_id = :organization_id ORDER BY id`. Revoked rows **included** (no `revoked_at IS NULL` filter).
- `revoke(int $organizationId, int $id): bool`: a single `UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE organization_id = :organization_id AND id = :id AND revoked_at IS NULL`; return `rowCount() === 1`. No read-then-write, so two concurrent revokes change the row once. Use the same timestamp expression/timezone convention as other `*_at` writes in the repositories (check e.g. the refresh-token revoke for UTC handling).
- Reword the class and `create()` docblocks: hashing and scope validation happen in `Pidgey\Auth\ApiTokens` / `ApiTokenRules`, not "the service".

Tests (integration, extend the existing file): list ordering by id, revoked rows included, other organizations' rows excluded; `revoke()` returns `true` then `false` on the same row, keeps the first `revoked_at`, returns `false` for another organization's id and leaves that row untouched; `create()` round-trip unchanged.

## Files to Change
- `source/lib/Repository/ApiTokenRepository.php` — add `listByOrganization()` and `revoke()`; docblock wording.
- `source/tests/integration/Repository/ApiTokenRepositoryTest.php` — new cases.
