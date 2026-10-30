# Issue: Persistence: API tokens table, entity and repository

## Description

Part of epic #84; depends on #86 (merged). Create the `api_tokens` table with its entity and repository, following [persistence.md](docs/agents/specs/persistence.md) and [organizations/data-model/api-tokens.md](docs/agents/specs/organizations/data-model/api-tokens.md).

## Problem

The API token endpoints and Bearer authentication of the `/v1` sending API need a place to store organization API tokens (digest, display prefix, scopes, usage and revocation times). No table, entity or repository exists for them yet.

## Expected Behavior

- Migration `source/migrations/0007_create_api_tokens.sql` creates `api_tokens` exactly as the table spec defines it: columns, types, nullability, defaults, `uq_api_tokens_token_hash`, `ix_api_tokens_organization_id` and `fk_api_tokens_organization_id` (`ON DELETE RESTRICT`), `InnoDB` / `utf8mb4_0900_ai_ci`. `0007` is the next free number now that #87 is merged; it is renumbered before merging only if #89 lands first.
- `Pidgey\Model\ApiToken` is a `final readonly` entity with one property per column. `scopes` is a `list<string>`, read through `Row::stringList()`, so invalid JSON or a non-list throws. `lastUsedAt` and `revokedAt` are `?DateTimeImmutable`.
- `Pidgey\Repository\ApiTokenRepository` provides:
  - `create(int $organizationId, string $name, string $tokenPrefix, string $tokenHash, list<string> $scopes): ApiToken`. It JSON-encodes `scopes` with `JSON_THROW_ON_ERROR` and re-reads the row by id, so `created_at` comes from the database default (it is not compared in PHP). It does not validate the scope names: that is the service's job.
  - `findInOrganization(int $organizationId, int $id): ?ApiToken`.
  - `findByTokenHash(string $tokenHash): ?ApiToken`. It is unscoped, and its doc comment cites [api/authentication/api-tokens.md](docs/agents/specs/api/authentication/api-tokens.md) as the spec that requires crossing organizations. It also returns revoked tokens: rejecting them is up to the caller.
- `Container::apiTokenRepository()` is a memoized getter.
- `Fixtures::apiToken()` inserts with plain SQL, with named overrides. By default it creates an organization, uses a unique digest, the `pdg_` prefix and scopes `["send"]`, and returns the id.
- `SchemaTest` covers `api_tokens`: columns, the unique key, the plain index, the foreign key and the collation.

## Solution

Follow the same patterns as #86 and #87: a `COLUMNS` constant, a private `fetchOne()`, `create()` re-reading through a private by-id lookup and throwing `LogicException` on read-back failure, and docblocks citing the specs.

Tests:
- A unit test for `ApiToken::fromRow()`. It covers every column type, nullable `last_used_at` / `revoked_at`, a missing column, invalid `scopes` JSON and `scopes` that is not a list of strings, each of which throws.
- Integration tests for every repository method:
  - not found returns `null`;
  - organization scoping: another organization's token is not found by `findInOrganization`;
  - `findByTokenHash` finds a token across organizations, including a revoked one;
  - a duplicate `token_hash` raises `PDOException`;
  - `scopes` survive the round trip;
  - an unknown `organization_id` raises `PDOException` (FK).

Security: only the SHA-256 digest and the non-secret prefix are stored. The repository never receives the plain token, and a `security` agent review is required before merge.

Out of scope (added by the endpoint issues that need them):
- token issuing, Bearer authentication and the API token endpoints;
- `listByOrganization`, `revoke` and `touchLastUsed`;
- entity helpers such as `ApiToken::isRevoked()`.

## Benefits

This unblocks the API token admin endpoints and Bearer authentication for `POST /v1/messages`, with organization isolation and digest-only storage enforced at the persistence layer.
