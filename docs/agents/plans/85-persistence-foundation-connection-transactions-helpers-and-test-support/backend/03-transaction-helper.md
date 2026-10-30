# Transaction helper
Add `Pidgey\Infra\Transaction` (final, constructed with a `PDO`) with `run(callable $work): mixed`, per `persistence/connection.md#pidgeyinfratransaction`:

- Throws `LogicException` if `$pdo->inTransaction()` is already true (no nesting).
- `beginTransaction()`, run `$work`, `commit()`, return `$work`'s result.
- On any `Throwable`: if still in a transaction, `rollBack()`; if `rollBack()` itself throws, ignore that failure; always rethrow the original exception unchanged.

Tests:
- Unit, with a PDO test double: commit path returns the result; exception path rolls back and rethrows the same instance; `rollBack()` throwing still rethrows the original; nesting rejected.
- Integration (truncation-based, uses `DatabaseCleaner` from step 05, or a temporary table created before the transaction in `setUp`): committed writes are visible from a second connection; rolled-back writes are not.

## Files to Change
- `source/lib/Infra/Transaction.php` — new.
- `source/tests/unit/Infra/TransactionTest.php` — new.
- `source/tests/integration/Infra/TransactionTest.php` — new.
