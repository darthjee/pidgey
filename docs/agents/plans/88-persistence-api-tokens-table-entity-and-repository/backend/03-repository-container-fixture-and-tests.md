# 03 — Repository, container, fixture and tests

## `Pidgey\Repository\ApiTokenRepository`

The repository is `final`, constructed with `PDO`, with
`private const COLUMNS = 'id, organization_id, name, token_prefix, token_hash, scopes, created_at, last_used_at, revoked_at'`
and a private `fetchOne()`, as in `UserRepository`.

- `create(int $organizationId, string $name, string $tokenPrefix, string $tokenHash, array $scopes): ApiToken`
  - Document `@param list<string> $scopes`.
  - Insert `organization_id, name, token_prefix, token_hash, scopes` with named
    placeholders. `scopes` is `json_encode($scopes, JSON_THROW_ON_ERROR)`, and
    `created_at` comes from the database default.
  - Re-read with `findInOrganization($organizationId, $id)`, and throw `LogicException`
    when the row cannot be read back.
  - Do not validate the scope names.
  - `@throws PDOException`: duplicate digest or unknown organization.
  - `@throws JsonException`.
- `findInOrganization(int $organizationId, int $id): ?ApiToken`: filters on both
  `organization_id` and `id`.
- `findByTokenHash(string $tokenHash): ?ApiToken`
  - Unscoped. The doc comment says so and cites `api/authentication/api-tokens.md`: the
    token itself identifies the organization.
  - It returns revoked tokens too; rejecting them is the caller's job.

The class docblock states that only the SHA-256 hex digest and the non-secret prefix are
stored, and that hashing happens in the service.

## Container

Add a memoized `Container::apiTokenRepository(): ApiTokenRepository` built on `pdo()`,
following `userRepository()`, with a matching case in `source/tests/unit/ContainerTest.php`
(same instance on repeated calls, right type).

## Fixture

Add `Fixtures::apiToken(?int $organizationId = null, ?string $name = null, string $tokenPrefix = 'pdg_', ?string $tokenHash = null, array $scopes = ['send'], ?string $lastUsedAt = null, ?string $revokedAt = null): int`.

- It inserts with plain SQL through `insert()`.
- It creates an organization when none is given, and uses a unique name
  (`unique('token')`) and `digest()`.
- `scopes` is JSON-encoded, and the dates are UTC `Y-m-d H:i:s` strings.

## Integration tests

Add `source/tests/integration/Repository/ApiTokenRepositoryTest.php`, extending
`RepositoryTestCase`, with no literal ids and no DDL.

`create`:
- returns the entity with every field set: `scopes` as `['send']`, `createdAt` set,
  `lastUsedAt` / `revokedAt` null;
- several scopes round-trip in order;
- a duplicate `token_hash` raises `PDOException`;
- an unknown organization id (one created and then known to be absent is not possible
  without DDL, so use an id beyond `MAX(id)` read from the table) raises `PDOException`
  through the FK.

`findInOrganization`:
- finds the fixture's token;
- returns `null` for an unknown id;
- returns `null` for a token of another organization.

`findByTokenHash`:
- finds a token of any organization;
- returns `null` for an unknown digest;
- returns a revoked token (fixture with `revokedAt`) with `revokedAt` set.

## Files to Change

- `source/lib/Repository/ApiTokenRepository.php`: new.
- `source/lib/Container.php`: `apiTokenRepository()`.
- `source/tests/unit/ContainerTest.php`: getter test.
- `source/tests/support/Fixtures.php`: `apiToken()`.
- `source/tests/integration/Repository/ApiTokenRepositoryTest.php`: new.
