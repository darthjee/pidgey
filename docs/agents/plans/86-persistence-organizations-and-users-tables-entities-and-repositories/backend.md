# Backend Plan: Persistence: organizations and users tables, entities and repositories

Issue: [86-persistence-organizations-and-users-tables-entities-and-repositories.md](../../issues/86-persistence-organizations-and-users-tables-entities-and-repositories.md)

## Overview
Add migrations `0001_create_organizations.sql` and `0002_create_users.sql`; entities `Pidgey\Model\Organization`, `Pidgey\Model\User` and enum `UserStatus`; repositories `OrganizationRepository` and `UserRepository` with memoized container getters; `Fixtures::organization()` / `Fixtures::user()`; `SchemaTest` expectations; and unit and integration tests.

## Context
- Specs (the source of truth): [persistence/schema-migrations.md](../../specs/persistence/schema-migrations.md), [persistence/entities.md](../../specs/persistence/entities.md), [persistence/repositories.md](../../specs/persistence/repositories.md), [persistence/testing.md](../../specs/persistence/testing.md), [organizations/data-model/organizations-and-users.md](../../specs/organizations/data-model/organizations-and-users.md), [organizations/data-model/conventions.md](../../specs/organizations/data-model/conventions.md).
- Foundation from #85 already on `main`: `Pidgey\Container` (`pdo()`, `transaction()`), `Pidgey\Model\Row` (`int`, `string`, `bool`, `dateTime`, `enum`, …), `Pidgey\Repository\Sql::dateTime()`, and `tests/support/{RepositoryTestCase,Fixtures,DatabaseCleaner}.php`. `Fixtures` has `unique(string $prefix)` and a generic `insert(string $table, array $values): int`. `tests/integration/Schema/SchemaTest.php` has a `TABLES` constant and `testTableSet()`.
- Decisions from the issue discussion:
  - `UserRepository::create(int $organizationId, string $username, string $email, string $passwordDigest, UserStatus $status, bool $isAdmin = false)`;
  - entities have no helper methods (no `isApproved()`);
  - repositories store values as given, with no trimming. A trailing space is a distinct value (`utf8mb4_0900_ai_ci` is NO PAD), and one test documents this.
- `Fixtures::user()` defaults `status` to `approved`.

## Steps

- [01 — Migrations and schema test](backend/01-migrations-and-schema-test.md)
- [02 — Entities and UserStatus enum](backend/02-entities.md)
- [03 — Repositories and container getters](backend/03-repositories-and-container.md)
- [04 — Fixtures and repository integration tests](backend/04-fixtures-and-repository-tests.md)

## CI Checks
- `source`: `composer lint` (CI job: backend lint)
- `source`: `composer db:prepare && composer coverage` (CI job: backend tests, with MySQL 9)
- Locally, through the dev containers: `composer tests` (runs `db:prepare` against `pidgey_test`).

## Notes
- Migrations keep the numbers 0001/0002: this issue merges before #87–#89. Once merged, a migration file is never edited (the runner checksums it).
- Never assert literal ids: `AUTO_INCREMENT` is not rolled back.
- Repository tests never run DDL (the rollback isolation would break).
- Case- and accent-insensitivity comes from the column collation. Do not use `LOWER()` in queries.
- `MixedIsolationTest` counts rows across every application table. With real tables it should still pass, because it truncates them first; check that it does.
