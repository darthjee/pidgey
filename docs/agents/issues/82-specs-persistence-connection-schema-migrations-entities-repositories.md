# Specs: persistence — connection, schema migrations, entities, repositories

## Context

The database infrastructure (connection factory, migrations runner, `bin/` scripts) is already implemented in `Pidgey\Infra`, and the tables are specified in `organizations/data-model/`. However, `source/migrations/` is empty, and no spec describes how application code uses the database: the shape of entities, repositories, transactions, the initial migration files, or how repositories are tested.

This issue adds those specs, so the schema, entity and repository work can later be split into implementation issues.

## What needs to be done

Docs only — a new spec area `docs/agents/specs/persistence.md` plus `docs/agents/specs/persistence/`:

- **connection.md**: `PdoFactory` options (adds a UTC session time zone through `ATTR_INIT_COMMAND`), one PDO per request through `Container::pdo()`, transactions owned by handlers/services, and a `Pidgey\Infra\Transaction::run()` helper (no nesting).
- **schema-migrations.md**: one `CREATE TABLE` per file, file names and foreign-key order (`0001_create_organizations` … `0008_create_smtp_connections`), explicit engine/charset/collation, naming for indexes and constraints.
- **entities.md**: `Pidgey\Model\` `final readonly` classes with `fromRow()`, a shared `Row` conversion helper, backed enums for `ENUM` columns, secrets held only in stored form.
- **repositories.md**: one repository per table, fixed column lists, method naming, organization scoping, `FOR UPDATE` locking, `now` as a bound parameter, the minimal methods for the first implementation.
- **testing.md**: transaction-rollback isolation for repository tests, `DatabaseCleaner` for code that owns transactions, plain-SQL `Fixtures`, a `SchemaTest` against `information_schema`.

Also index the new spec in `docs/agents/specs.md` and link it from `architecture/database.md` and `architecture/backend.md`.

### Decisions taken

- Entities: readonly classes with `fromRow()`.
- Repository test isolation: transaction rollback.
- Spec area name: `persistence` (`architecture/database.md` stays as is and is linked).

### Out of scope

- Any code change (migrations, entities, repositories, the `PdoFactory` time zone) — these go into a follow-up implementation epic.
- The SMTP password encryption mechanism and the database-level "one active SMTP connection" constraint stay pending in `email/smtp-connections.md`.

## Acceptance criteria

- [ ] `docs/agents/specs/persistence.md` exists and indexes the five sub-specs.
- [ ] `docs/agents/specs/persistence/connection.md`, `schema-migrations.md`, `entities.md`, `repositories.md` and `testing.md` exist and cover the scope above.
- [ ] `docs/agents/specs.md` indexes the new `persistence` area.
- [ ] `architecture/database.md` and `architecture/backend.md` link to the persistence spec.
- [ ] No code changes are made.
