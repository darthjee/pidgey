# Plan: Persistence foundation: connection, transactions, helpers and test support

Issue: [85-persistence-foundation-connection-transactions-helpers-and-test-support.md](../../issues/85-persistence-foundation-connection-transactions-helpers-and-test-support.md)

## Overview
Add the shared persistence base the table sub-issues (#86–#89) build on: the UTC session time zone in `PdoFactory`, `Pidgey\Container` with `pdo()` / `transaction()`, the `Transaction`, `Row` and `Sql` helpers, and the integration test support (rollback-based `RepositoryTestCase`, `Fixtures`, `DatabaseCleaner`, `SchemaTest`). No table is created and `index.php` is untouched. All work is in `source/`, owned by the backend agent.

See [backend.md](backend.md) for the full plan.
