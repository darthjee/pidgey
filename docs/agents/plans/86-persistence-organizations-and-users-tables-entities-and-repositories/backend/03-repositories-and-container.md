# Repositories and container getters

Add the repositories per [repositories.md](../../../specs/persistence/repositories.md). Both are `final class` with `__construct(private readonly PDO $pdo)`, a private `COLUMNS` constant (no `SELECT *`) and named placeholders only. They never begin or commit a transaction and let `PDOException` propagate.

`Pidgey\Repository\OrganizationRepository`:

- `create(string $name): Organization`: insert, then re-read by `lastInsertId()` through `findById()`;
- `findById(int $id): ?Organization`.

`Pidgey\Repository\UserRepository`:

- `create(int $organizationId, string $username, string $email, string $passwordDigest, UserStatus $status, bool $isAdmin = false): User`:
  - binds `$status->value` and the boolean as an int;
  - stores values as given (no trimming);
  - re-reads by id.
- `findById(int $id): ?User`, `findByUsername(string $username): ?User` and `findByEmail(string $email): ?User`:
  - unscoped;
  - each doc comment names why it crosses organizations (login, account recovery, sessions; see [repositories.md#organization-scoping](../../../specs/persistence/repositories.md#organization-scoping));
  - plain `WHERE username = :username`, relying on the column collation for case and accent insensitivity.
- `findInOrganization(int $organizationId, int $id): ?User`: scoped by `organization_id`.

Container:

- add `organizationRepository(): OrganizationRepository` and `userRepository(): UserRepository` to `Pidgey\Container`, each memoized (`??=`) and built on `pdo()`, with docblocks matching the existing getters.

Unit container tests in `source/tests/unit/ContainerTest.php`:

- each getter returns the same instance on repeated calls;
- it builds on the factory's PDO, which is created only once.

## Files to Change
- `source/lib/Repository/OrganizationRepository.php` — new.
- `source/lib/Repository/UserRepository.php` — new.
- `source/lib/Container.php` — two memoized getters.
- `source/tests/unit/ContainerTest.php` — getter tests.
