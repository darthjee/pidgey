# Issue: Persistence: organizations and users tables, entities and repositories

## Description

Part of epic #84; depends on #85 (container, `Row`, `Sql`, `RepositoryTestCase`, `Fixtures`, `SchemaTest` scaffolding). Create the `organizations` and `users` tables with their entities and repositories, following [persistence.md](docs/agents/specs/persistence.md) and [organizations/data-model/organizations-and-users.md](docs/agents/specs/organizations/data-model/organizations-and-users.md). Sub-issues #87, #88 and #89 depend on this one.

## Problem

No application table exists yet: `source/migrations/` is empty and there is no `Pidgey\Model` or `Pidgey\Repository` code. Every other table references `organizations` or `users`, so these two must land first.

## Expected Behavior

- `composer db:migrate` creates `organizations` and `users` exactly as specified (columns, types, nullability, defaults, `uq_users_username`, `uq_users_email`, `ix_users_organization_id`, `fk_users_organization_id` with `ON DELETE RESTRICT`, `utf8mb4_0900_ai_ci`).
- `Organization` and `User` entities are built from rows through `fromRow()`.
- Repositories create and look up rows, with organization scoping where required.
- `username` and `email` are unique and looked up case- and accent-insensitively, through the column collation (no `LOWER()`).

## Solution

### Migrations

- `source/migrations/0001_create_organizations.sql` and `0002_create_users.sql`, per [persistence/schema-migrations.md](docs/agents/specs/persistence/schema-migrations.md) (one `CREATE TABLE` per file, explicit engine/charset/collation, explicit index and constraint names, no `IF NOT EXISTS`). They keep the numbers 0001 and 0002 because this issue merges before #87–#89.

### Entities (`Pidgey\Model`)

- `Organization` and `User`: `final readonly` classes with promoted public properties and `fromRow()` built on `Row`, per [persistence/entities.md](docs/agents/specs/persistence/entities.md).
- `UserStatus` string-backed enum (`Pending`, `Approved`, `Denied`).
- **No helper methods yet** (`isApproved()` etc.): endpoint issues add them when they need them.

### Repositories (`Pidgey\Repository`)

- `OrganizationRepository`: `create(string $name): Organization`, `findById(int $id): ?Organization`.
- `UserRepository`:
  - `create(int $organizationId, string $username, string $email, string $passwordDigest, UserStatus $status, bool $isAdmin = false): User`. `status` has no default and is always explicit; `isAdmin` defaults to `false`.
  - `findById(int $id)`, `findByUsername(string $username)`, `findByEmail(string $email)`: unscoped, with doc comments naming the login/recovery/session specs that need them.
  - `findInOrganization(int $organizationId, int $id)`: scoped.
- Both use a private `COLUMNS` constant and named placeholders. `create()` re-reads the row by id.
- **Repositories store values as given**: no trimming or normalization. Trimming `username`/`email` is the job of the registration/login endpoints.
- Container getters `Container::organizationRepository()` and `Container::userRepository()`, memoized and built on `pdo()`.

### Test support

- `Fixtures::organization(...)` and `Fixtures::user(...)` (plain SQL; `user()` creates an organization when `organizationId` is not given; unique default `username`/`email`; default `status` `approved`).
- `SchemaTest` expectations for both tables.

### Tests

- Unit: `fromRow()` for both entities (types, `is_admin` bool, enum, UTC datetimes, missing column and unknown status throwing).
- Integration (`RepositoryTestCase`, rollback):
  - every method, including not-found returning `null`;
  - `findInOrganization` does not find a user of another organization;
  - duplicate `username` / `email` raises `PDOException`, including `Admin`/`admin` and `José`/`jose`;
  - `findByUsername` / `findByEmail` match regardless of case and accents;
  - a trailing space is a distinct value (`admin ` ≠ `admin`, NO PAD collation), documenting that normalization belongs to the endpoints;
  - `create()` without `isAdmin` yields `isAdmin === false`.
- No literal ids in assertions (`AUTO_INCREMENT` is not rolled back).

### Out of scope

- Registration, login, bootstrap and any endpoint logic.
- Entity helper methods.
- Input trimming and normalization.

## Benefits

Unblocks #87, #88 and #89 and every authentication and organization endpoint, and sets the pattern (migration, entity, repository, fixtures, schema test) that the remaining tables follow.
