# Container with pdo() and transaction()
Add `Pidgey\Container` (root namespace, composition root per `request-handler/handlers.md#pidgeycontainer`):

- `__construct(ConnectionFactory $factory = new PdoFactory())` — no required arguments.
- `pdo(): PDO` — on first call reads `DatabaseConfig::fromEnv()` and calls `$factory->create($config)`; memoized. A missing `DB_*` variable surfaces `DatabaseConfig`'s error (names the variable, not a value).
- `transaction(): Transaction` — memoized, built on `pdo()`.
- Do **not** touch `source/public/index.php`.

Tests:
- Unit, with a `ConnectionFactory` double: nothing is created by the constructor; `pdo()` calls the factory once and returns the same instance twice; `transaction()` returns the same instance twice and uses that PDO.
- Integration: `(new Container())->pdo()` connects to `pidgey_test`.

## Files to Change
- `source/lib/Container.php` — new.
- `source/tests/unit/ContainerTest.php` — new.
- `source/tests/integration/ContainerTest.php` — new.
