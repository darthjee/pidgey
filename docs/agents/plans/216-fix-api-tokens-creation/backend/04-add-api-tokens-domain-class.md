# Add the ApiTokens domain class and Container wiring

Orchestrate token generation, persistence and revocation in `Pidgey\Auth`, keeping handlers thin.

`Pidgey\Auth\ApiTokenRevokeOutcome` enum: `Revoked`, `AlreadyRevoked`, `NotFound`.

`Pidgey\Auth\ApiTokens` (final), constructed with `ApiTokenRepository`:

- `list(int $organizationId): array` → `listByOrganization()`.
- `create(int $organizationId, string $name, array $scopes): CreatedApiToken` — inputs are already valid (from `ApiTokenRules`): `$plain = ApiTokenValue::generate()`; `repository->create($organizationId, $name, ApiTokenValue::PREFIX, ApiTokenValue::hash($plain), $scopes)`; return `new CreatedApiToken($token, $plain)`. No fallible work after the insert; no transaction.
- `revoke(int $organizationId, int $id): ApiTokenRevokeOutcome` — `repository->revoke()` true → `Revoked`; otherwise `findInOrganization()` null → `NotFound`, else `AlreadyRevoked`.

`Container::apiTokens(): ApiTokens` — memoized like `registration()` / `login()`, built from `apiTokenRepository()`.

Tests:

- Integration `source/tests/integration/Auth/ApiTokensTest.php` (DB, like `RegistrationTest`): create stores `token_prefix = 'pdg_'` and `token_hash = sha256(plain)`, and no column contains the plain token; returned plain token matches the format; list scoping and order; revoke outcomes `Revoked`, then `AlreadyRevoked`, `NotFound` for a foreign or unknown id; an exception thrown from the repository during create does not carry the plain token in its trace arguments (`#[SensitiveParameter]`).
- Unit: extend `source/tests/unit/Container/ContainerAuthTest.php` for `apiTokens()` memoization.

## Files to Change
- `source/lib/Auth/ApiTokenRevokeOutcome.php` — new.
- `source/lib/Auth/ApiTokens.php` — new.
- `source/lib/Container.php` — add `apiTokens()` and its memo property.
- `source/tests/integration/Auth/ApiTokensTest.php` — new.
- `source/tests/unit/Container/ContainerAuthTest.php` — new case.
