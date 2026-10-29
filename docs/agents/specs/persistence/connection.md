# Connection

Purpose: define how application code obtains its database connection and who owns transactions.

The access rules (raw PDO, prepared statements, no multi-statements, environment-only
settings) are in [architecture/database.md](../architecture/database.md#access).

## Factory

The connection is built by the existing `Pidgey\Infra\PdoFactory` (implementing
`Pidgey\Infra\ConnectionFactory`) from `Pidgey\Infra\DatabaseConfig::fromEnv()`.

- `create(DatabaseConfig)` connects to `DB_NAME`; `createServer(DatabaseConfig)` connects with
  no database selected (used only by the database scripts).
- Options, on every connection:

  | Option | Value | Why |
  | --- | --- | --- |
  | `PDO::ATTR_ERRMODE` | `ERRMODE_EXCEPTION` | Every SQL error throws `PDOException`. |
  | `PDO::ATTR_DEFAULT_FETCH_MODE` | `FETCH_ASSOC` | Rows are column-keyed arrays, the input of `fromRow()` ([entities.md](entities.md)). |
  | `PDO::ATTR_EMULATE_PREPARES` | `false` | Native prepares; integer columns come back as PHP `int`. |
  | `Pdo\Mysql::ATTR_MULTI_STATEMENTS` | `false` | No stacked statements. |
  | `Pdo\Mysql::ATTR_INIT_COMMAND` | `SET time_zone = '+00:00'` | **New.** Column defaults (`CURRENT_TIMESTAMP`) and `ON UPDATE` are written in UTC, matching the `DATETIME`-holds-UTC convention ([data-model/conventions.md](../organizations/data-model/conventions.md#timestamps)). |

- The DSN sets `charset=utf8mb4`.

## One connection per request

- `Pidgey\Container::pdo(): PDO` builds the connection on its first call (lazy) and returns
  the same instance for the rest of the request (memoized), like every container getter
  ([request-handler/handlers.md](../request-handler/handlers.md#pidgeycontainer)).
- A request that never touches the database never connects (e.g. `GET /status.json`).
- No persistent connections (`PDO::ATTR_PERSISTENT` is not set): a session-scoped state
  (time zone, locks, an open transaction) never leaks between requests.
- Every repository of a request receives that same PDO, so they all share its transaction.
- The database scripts (`source/bin/`) do not use the container; they keep building their
  connections through `Pidgey\Infra\Cli\DatabaseCommand`.

## Transactions

- **Repositories never begin, commit or roll back.** The caller owns the transaction: the
  handler, or a service it calls ([request-handler/handlers.md](../request-handler/handlers.md):
  handlers own their transactions and commit inside `process()`).
- Transactions are not nested. Code that runs inside a transaction never opens another one.
- `SELECT … FOR UPDATE` repository methods are called only inside a transaction (see
  [repositories.md](repositories.md#locking)).

### `Pidgey\Infra\Transaction`

A small helper so every caller begins, commits and rolls back the same way:

```php
final class Transaction
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function run(callable $work): mixed;
}
```

- `run()` calls `beginTransaction()`, runs `$work`, commits and returns its result.
- On any `Throwable` it rolls back (when a transaction is still active) and rethrows the
  original exception unchanged.
- It throws `LogicException` when a transaction is already active on the PDO (no nesting).
- Exposed as `Container::transaction(): Transaction`, built on `Container::pdo()`.

## Pending decisions

None.
