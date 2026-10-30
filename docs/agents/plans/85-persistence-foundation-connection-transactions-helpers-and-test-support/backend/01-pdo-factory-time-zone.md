# UTC session time zone in PdoFactory
Add `Pdo\Mysql::ATTR_INIT_COMMAND => "SET time_zone = '+00:00'"` to `PdoFactory::options()`, so every connection (`create()` and `createServer()`) writes `CURRENT_TIMESTAMP` defaults in UTC. Signatures and the `ConnectionFactory` interface stay unchanged.

Tests:
- Extend the unit test `testOptionsHardenTheConnection` to assert the init command.
- Integration: a connection from `create()` and one from `createServer()` both report `SELECT @@session.time_zone` = `+00:00`.

## Files to Change
- `source/lib/Infra/PdoFactory.php` — add the init command option.
- `source/tests/unit/Infra/PdoFactoryTest.php` — assert the new option.
- `source/tests/integration/Infra/PdoFactoryTest.php` — assert the session time zone on both connection kinds.
