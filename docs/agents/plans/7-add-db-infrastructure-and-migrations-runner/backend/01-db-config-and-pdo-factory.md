# DB configuration and PDO factory

Add the DB settings value object and the PDO factory every other piece builds on.

- `Pidgey\Infra\DatabaseConfig` (final, readonly): `host`, `port`, `name`, `user`, `password`.
  A static `fromEnv()` reads `DB_HOST`, `DB_NAME`, `DB_USER` via `Env::required()`, `DB_PORT`
  via `Env::get()` defaulting to `3306` (validated as a positive integer), `DB_PASSWORD` via
  `Env::get()` defaulting to `''`. No `PHP_ENV`-based switching.
- `Pidgey\Infra\PdoFactory`:
  - `create(DatabaseConfig $config): PDO` — DSN
    `mysql:host=<host>;port=<port>;dbname=<name>;charset=utf8mb4`.
  - `createServer(DatabaseConfig $config): PDO` — same DSN without `dbname` (used by wait,
    create, drop).
  - Options on both: `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`,
    `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`, `PDO::ATTR_EMULATE_PREPARES => false`,
    and `Pdo\Mysql::ATTR_MULTI_STATEMENTS => false` (PHP 8.4 constant; equivalent to the
    deprecated-in-8.5 `PDO::MYSQL_ATTR_MULTI_STATEMENTS`) so no query can carry stacked
    statements.
  - Keep the DSN/options building in small public-for-test or pure methods (e.g.
    `dsn(DatabaseConfig, bool $withDatabase)`, `options()`) so they can be unit-tested without a
    server.
- Unit tests: `fromEnv()` reads every variable, applies the `DB_PORT`/`DB_PASSWORD` defaults,
  throws on a missing required variable and on an invalid port; DSN with and without database;
  options contain the four settings above.

## Files to Change
- `source/lib/Infra/DatabaseConfig.php` — new.
- `source/lib/Infra/PdoFactory.php` — new.
- `source/tests/unit/Infra/DatabaseConfigTest.php` — new.
- `source/tests/unit/Infra/PdoFactoryTest.php` — new.
