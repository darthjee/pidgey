# Backend Plan: Persistence: auth token tables, entities and repositories

Main plan: [plan.md](plan.md)

## Shared contracts

- Implement the four `create()` signatures from [plan.md](plan.md#shared-contracts) exactly.
- Rely on the product owner's explicit-timestamp rule in
  `docs/agents/specs/persistence/repositories.md`.

## Context

- #85 and #86 are merged. Copy their patterns:
  - `source/migrations/0002_create_users.sql`;
  - `Pidgey\Model\User` / `UserStatus` and `Pidgey\Repository\UserRepository` (`COLUMNS`
    constant, private `fetchOne()`, re-read after insert, docblocks naming the spec behind
    unscoped lookups);
  - the `Container` memoized getters;
  - `Fixtures::user()`;
  - `SchemaTest`'s `columns()`, `uniqueKeys()`, `indexes()`, `foreignKeys()` and
    `collation()` helpers.
- `Row`: a missing column throws `LogicException`; an unknown enum value throws
  `UnexpectedValueException`.
- `Sql::dateTime()` binds a `DateTimeImmutable` as UTC `Y-m-d H:i:s`.
- The next free migration numbers are `0003`–`0006`.

## Steps

- [01 — Migrations and schema test](backend/01-migrations-and-schema-test.md)
- [02 — Enums and entities](backend/02-entities.md)
- [03 — Repositories and container](backend/03-repositories-and-container.md)
- [04 — Fixtures and repository tests](backend/04-fixtures-and-repository-tests.md)

## CI Checks

- `source`: `composer lint` (CI job: backend lint)
- `source`: `composer db:prepare && composer coverage` (CI job: backend tests, mysql:9 + mailpit)

## Notes

- **Security:** repositories, entities and fixtures handle only SHA-256 hex digests.
  - No plain token appears in any argument, default, log message or exception message.
  - Exception messages name ids, never digests.
  - A `security` agent review is required before merge.
- **Migration numbers:** renumber `0003`–`0006` to the next free numbers right before
  merging if #88 or #89 merges first. Never edit a merged migration.
- **Ids:** `AUTO_INCREMENT` is not rolled back, so tests never assert literal ids.
- **Deferred:** entity helper methods, the lockout upsert/`lockByUserId()`, and every
  update/revoke/transition method. They come with the endpoint issues.
