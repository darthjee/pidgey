# Fixtures and repository integration tests

Add the fixture methods to `Pidgey\Tests\Support\Fixtures`, using plain SQL through the existing `insert()`. Each takes named overrides and returns the id.

- `organization(?string $name = null): int`: the default name comes from `unique('organization')`.
- `user(?int $organizationId = null, ?string $username = null, ?string $email = null, string $passwordDigest = <a fixed password_hash value or a literal digest>, bool $isAdmin = false, string $status = 'approved'): int`:
  - creates an organization when `$organizationId` is null;
  - the default username comes from `unique('user')`;
  - the default email is `unique('user') . '@example.com'`.

  Any equivalent signature (e.g. an `array $overrides`) is fine as long as it follows [testing.md#fixtures](../../../specs/persistence/testing.md#fixtures).

Integration tests in `source/tests/integration/Repository/`, extending `RepositoryTestCase` (rollback isolation, no DDL, no literal ids):

`OrganizationRepositoryTest`:

- `create()` returns the entity with the id it got back and `createdAt`/`updatedAt` set;
- `findById()` finds a fixture row;
- `findById()` returns `null` for an id that does not exist (e.g. created id + 1000000).

`UserRepositoryTest`:

- `create()`:
  - returns every field;
  - `isAdmin` is `false` when omitted and `true` when passed;
  - the status is stored as passed.
- `findById()`, `findByUsername()`, `findByEmail()`, `findInOrganization()` find the fixture row and return `null` when not found.
- `findInOrganization()` returns `null` for a user of another organization.
- Duplicates raise `PDOException`:
  - a duplicate `username` or `email`;
  - case variants `Admin`/`admin`;
  - accent variants `José`/`jose`.
- Lookups match regardless of case and accents: `findByUsername('ADMIN')` finds `admin`, `findByEmail('JOSE@example.com')` finds `josé@example.com`.
- A trailing space is a distinct value: `findByUsername('admin ')` returns `null` for `admin`, and creating `admin ` next to `admin` succeeds. This documents the NO PAD collation and that trimming belongs to the endpoints.
- Creating a user with a non-existent `organization_id` raises `PDOException` (foreign key).

Keep `MixedIsolationTest` passing.

## Files to Change
- `source/tests/support/Fixtures.php` — `organization()`, `user()`.
- `source/tests/integration/Repository/OrganizationRepositoryTest.php` — new.
- `source/tests/integration/Repository/UserRepositoryTest.php` — new.
