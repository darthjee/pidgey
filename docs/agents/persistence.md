# Persistence

Purpose: index of the specs describing how the application code reaches the database: the runtime connection, the initial schema migrations, the entity classes, the repositories and how they are tested.

Database access rules, the database scripts and the migrations runner are specified in
[architecture/database.md](architecture/database.md); the tables themselves in
[organizations/data-model.md](organizations/data-model.md). This spec builds on both and
does not repeat them.

```
Handler / Service                      (owns the transaction)
  → Repository  (Pidgey\Repository\)   (repositories.md; SQL, prepared statements)
  → PDO         (Container::pdo())     (connection.md; one per request)
  ← row → Entity (Pidgey\Model\)       (entities.md; readonly, fromRow())
```

| File | Content |
| --- | --- |
| [connection.md](persistence/connection.md) | The runtime connection: `PdoFactory` options, UTC session time zone, one PDO per request through `Container::pdo()`, transaction ownership and the `Transaction` helper. |
| [schema-migrations.md](persistence/schema-migrations.md) | The initial migration files: one table per file, names, numbering and foreign-key order. |
| [entities.md](persistence/entities.md) | `Pidgey\Model\` entities: readonly classes, `fromRow()`, column-to-property mapping, PHP types for dates, booleans, enums and JSON. |
| [repositories.md](persistence/repositories.md) | `Pidgey\Repository\` classes: construction, column lists, finders, inserts, updates, locking, organization scoping, time parameters. |
| [testing.md](persistence/testing.md) | Repository and schema tests: transaction-rollback isolation, fixtures, what each table must test. |
