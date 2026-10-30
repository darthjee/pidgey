# Issue: Persistence foundation: connection, transactions, helpers and test support

## Description
Part of epic #84. Lay the foundation every table implementation builds on, as specified in [persistence/connection.md](docs/agents/specs/persistence/connection.md), [persistence/entities.md](docs/agents/specs/persistence/entities.md), [persistence/repositories.md](docs/agents/specs/persistence/repositories.md) and [persistence/testing.md](docs/agents/specs/persistence/testing.md). No table is created here.

## Problem
Every table sub-issue of #84 needs a shared base: a request-scoped connection with a UTC session time zone, a single way to run transactions, shared row/SQL conversion helpers, and test support (rollback isolation, fixtures, a cleaner, a schema test). None of it exists yet, and there is no `Pidgey\Container`.

## Expected Behavior
After this issue, a table sub-issue only adds its migration, entity, repository, a fixture method, schema expectations and a container getter; everything else is already in place and tested. No table is created here and the request flow (`index.php`) is unchanged.

## Solution
### Scope

- `PdoFactory`: add `Pdo\Mysql::ATTR_INIT_COMMAND => "SET time_zone = '+00:00'"` to the connection options.
- `Pidgey\Container`: `pdo(): PDO` (lazy, memoized, from `DatabaseConfig::fromEnv()`) and `transaction(): Transaction`. Create the container if it does not exist yet, following [request-handler/handlers.md](docs/agents/specs/request-handler/handlers.md#pidgeycontainer).
- `Pidgey\Infra\Transaction::run(callable)`: begin, commit, roll back on any `Throwable` and rethrow; `LogicException` when a transaction is already active.
- `Pidgey\Model\Row`: `int()`, `string()`, `bool()`, `dateTime()` (UTC), `enum()`, `stringList()` (JSON), nullable variants; `LogicException` on a missing column.
- `Pidgey\Repository\Sql::dateTime()`: format a `DateTimeImmutable` as `Y-m-d H:i:s` in UTC.
- Test support: `Pidgey\Tests\Support\RepositoryTestCase` (transaction per test, rolled back), `Fixtures` (empty, ready for per-table methods), `DatabaseCleaner::truncateAll()`, and `source/tests/integration/Schema/SchemaTest.php` asserting the table set (only `schema_migrations` for now).

### Tests

- Unit: `Row` (every conversion, UTC parsing, missing column, invalid enum/JSON), `Sql::dateTime()` (non-UTC input converted).
- Integration: `PdoFactory` connection has `@@session.time_zone = '+00:00'`; `Transaction` commits, rolls back and rethrows, rejects nesting (uses `DatabaseCleaner`); `Container::pdo()` returns the same instance.
- A test proving a rollback-based test still passes after a truncating one in the same run.

### Edge cases

- Existing `schema_migrations.applied_at` rows on developer databases were written in the server time zone; new rows are UTC. Cosmetic only.

### `Container`

- **Getters only:** #85 creates `Pidgey\Container` with `pdo()` and `transaction()`; it is not wired into `index.php`. Creating the container per request is the front controller's job ([front-controller.md](docs/agents/specs/request-handler/front-controller.md)), left to the router/`FrontController` work.
- **Construction:** `new Container()` with no required arguments; an optional `ConnectionFactory` (default `PdoFactory`) so tests can swap it.
- **Lazy config:** `DatabaseConfig::fromEnv()` is read on the first `pdo()` call, not in the constructor, so a request that never touches the database needs no `DB_*` variables.
- **Memoized:** `pdo()` and `transaction()` are built once and reused.
- **Tested:** same instance returned twice; nothing connects before the first `pdo()`; `transaction()` wraps that same PDO.

### `DatabaseCleaner`

- `truncateAll()` reads the tables from `information_schema.tables` (`table_schema = DATABASE()`, `table_type = 'BASE TABLE'`, excluding `schema_migrations`), so tables added by later sub-issues are cleaned with no list to maintain.
- Table names are backtick-quoted in each `TRUNCATE`.
- Foreign key checks are disabled before truncating and re-enabled in a `finally`.
- It refuses to run (`LogicException`) unless `SELECT DATABASE()` ends in `_test`, a second guard on top of `IntegrationTestCase`.
- Tested: it empties every application table, keeps `schema_migrations`, restores `FOREIGN_KEY_CHECKS`, and refuses a non-`_test` database.

### Backward compatibility

- **Every `PdoFactory` connection gets the init command:** `create()` and `createServer()`, so the migrations runner, `DatabaseServer`, the `bin/` scripts and the test helpers (`IntegrationTestCase`, `ThrowawayDatabase`) too. `SET time_zone` is session-scoped and needs no privilege, so none of them can break.
- **Use the offset `'+00:00'`, not the name `'UTC'`:** named zones need MySQL's time zone tables loaded; the numeric offset always works.
- **`schema_migrations.applied_at`** (`DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6)`) is now written in UTC. The `mysql:9` container already defaults to UTC, so nothing changes locally; it only matters on a server with another time zone. Existing rows are left as they are.
- **No public API change:** the `ConnectionFactory` interface and `PdoFactory` method signatures stay the same; `index.php` is not touched (the container is created but not wired into the request flow yet).
- **Existing tests keep passing;** `tests/unit/Infra/PdoFactoryTest::testOptionsHardenTheConnection` is extended to assert the init command.

### Performance & security

Security:

- **No values in error messages:** `Row` exceptions name the entity and the column, never the value (a row may hold `password_digest`, token hashes or SMTP ciphertext). Tested.
- **`Transaction` keeps the original error:** if `rollBack()` also fails (e.g. lost connection), the original exception is rethrown and the rollback failure is ignored. Nesting is detected with `PDO::inTransaction()`. Tested with a PDO whose `rollBack()` throws.
- **No secrets in connection details:** the password is passed to `new PDO()` separately, never in the DSN (as `PdoFactory` already does); a missing `DB_*` variable is reported by name, not value.
- **Guarded cleaner:** `DatabaseCleaner` only runs on a `_test` database.
- **No persistent connections:** session state (time zone, locks, open transactions) never carries over between requests.

Performance:

- The `SET time_zone` init command runs once per connection, in the handshake, only for requests that connect.
- The connection and its config are lazy: nothing happens until `pdo()` is called.
- Rollback-based isolation is cheap; `DatabaseCleaner` (one `information_schema` query plus the truncates) is only used by tests of code that owns its transactions.

### Out of scope

- Any table, entity or repository (sub-issues 2–5).

## Benefits
- Sub-issues #86–#89 build on one tested foundation instead of each inventing its own helpers.
- UTC timestamps and secret-free error messages are guaranteed in one place.
