# Database admin helpers (wait, create, drop)

Server-level operations used by the CLIs and by integration-test support.

- `Pidgey\Infra\DatabaseServer` (constructor: `PdoFactory`, `DatabaseConfig`, an injectable
  sleeper/clock so retries are unit-testable):
  - `waitUntilReady(int $timeoutSeconds): void` — repeatedly tries `createServer()` (catching
    `PDOException`), sleeping ~1 s between attempts; throws a clear
    "MySQL at <host>:<port> did not accept connections within <n>s: <last error>" on timeout.
  - `createDatabase(string $name): void` — ``CREATE DATABASE IF NOT EXISTS `<name>`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci``.
  - `dropDatabase(string $name): void` — ``DROP DATABASE IF EXISTS `<name>` ``.
  - Database names are identifiers, not bindable parameters: validate against
    `^[A-Za-z0-9_]{1,64}$` and throw otherwise, then backtick-quote.
- Unit tests: identifier validation; wait succeeds after N failures; wait times out with the
  message (fake connector + fake sleeper/clock).

## Files to Change
- `source/lib/Infra/DatabaseServer.php` — new.
- `source/tests/unit/Infra/DatabaseServerTest.php` — new.
