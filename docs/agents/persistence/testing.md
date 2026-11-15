# Persistence Testing

Purpose: define how the schema, entities and repositories are tested.

Suites, the `_test` database guard and `db:prepare` are in
[testing/backend.md](../testing/backend.md).

## Entities: unit suite

- Each entity has a unit test (`source/tests/unit/Model/`) for `fromRow()`: every column
  converted to the right type, nullable columns as `null`, a missing column and an unknown
  enum value throwing.
- `Pidgey\Model\Row` and `Pidgey\Repository\Sql` have their own unit tests (UTC parsing and
  formatting, including a non-UTC `DateTimeImmutable` input).

## Repositories: integration suite, transaction rollback

Repository tests live in `source/tests/integration/Repository/` and run against `pidgey_test`
with the real migrations applied.

- Each test runs **inside a transaction that is rolled back**: the base class
  `Pidgey\Tests\Support\RepositoryTestCase` (extending `IntegrationTestCase`) opens one PDO
  per test, calls `beginTransaction()` in `setUp()` and `rollBack()` in `tearDown()`. Nothing
  a test writes survives it, and tests do not depend on each other's data.
- Consequences every repository test must respect:
  - **no DDL** (`CREATE`, `ALTER`, `TRUNCATE`, …): MySQL commits implicitly before DDL, which
    would break isolation;
  - **no literal ids**: `AUTO_INCREMENT` is not rolled back, so tests use the ids they got
    back from `create()` or the fixtures;
  - the code under test must not open its own transaction (repositories never do, see
    [connection.md](connection.md#transactions)).
- `FOR UPDATE` methods are tested for their result inside the test transaction. Lock
  contention (a second connection blocking) is tested only where an endpoint spec relies on
  it, with a second PDO and `innodb_lock_wait_timeout` lowered for that session.

## Code that owns transactions

Handlers and services that use `Pidgey\Infra\Transaction` cannot run inside an outer test
transaction (no nesting). Their integration tests use
`Pidgey\Tests\Support\DatabaseCleaner::truncateAll()` in `setUp()` instead: it disables
foreign key checks, truncates every application table (never `schema_migrations`) and
re-enables them. `Transaction` itself is tested this way.

## Fixtures

- `Pidgey\Tests\Support\Fixtures` creates rows for tests: one method per table
  (`organization()`, `user()`, `smtpConnection()`, …), each taking named overrides and using
  valid defaults for the rest (unique values for unique columns, e.g. `user-<random>`), and
  returning the new id.
- Fixtures insert with **plain SQL**, not through the repository under test, so a broken
  `create()` cannot hide behind its own fixture.
- A fixture creates the rows it needs: `user()` without an `organizationId` override creates
  an organization first.

## Schema test

`source/tests/integration/Schema/SchemaTest.php` checks that the migrated `pidgey_test`
matches the table specs, through `information_schema`:

- the set of tables (the spec'd tables plus `schema_migrations`, nothing else);
- per table: column names, types, nullability and defaults;
- unique keys and foreign keys (columns, referenced table, `ON DELETE RESTRICT`);
- table collation `utf8mb4_0900_ai_ci`.

Each table's implementation adds its expectations to this test. It is read-only, so it needs
no isolation.

## What each table's implementation must test

- Migration: covered by the schema test; `composer db:status` shows no pending or edited file.
- Entity: `fromRow()` unit test.
- Repository: every method, including not-found (`null` / `false`), organization scoping (a
  row of another organization is not found, updated or deleted), unique-key violations
  surfacing as `PDOException`, and case-insensitive lookups where the table spec requires them
  (`users.username`, `users.email`).

## Pending decisions

None.
