# Update architecture and testing specs
Record the web root, migrations rules, PHP version and composer tooling.

- **`architecture/repository-layout.md`**: `dockerfiles/` row lists `dev_pidgey-base/`,
  `dev_pidgey/`, `circleci_pidgey-base/`, `pidgey/`; `source/public/` row notes the initial
  stub `index.php` returning a JSON health response (no routing yet); remove the web-root
  "Pending decision" line; add `source/tests/support/` and `source/tests/fixtures/` to the
  tests row.
- **`architecture/database.md`**: under Migrations, add:
  - a wait-for-db script in `scripts/` that blocks until MySQL accepts connections;
  - test-database creation before integration tests;
  - idempotency rules: re-running is a no-op; applied versions are never reapplied; a failing
    migration stops the run and is not recorded in `schema_migrations`; CI recreates the test
    database on every run;
  - the known limitation that MySQL DDL auto-commits, so a failed migration may leave partial
    changes (keep one DDL concern per file).
- **`architecture/dependencies.md`**: `php` (8.4).
- **`architecture/backend.md`**: "Plain PHP 8.4".
- **`testing/backend.md`**: composer scripts `tests`, `tests:unit`, `tests:integration`,
  `coverage`, `lint`, `lint:fix`, `docs`, `complexity`; phpcs (PSR-12 + slevomat, PHPDoc
  required outside tests); phpmd (unused-code gate in `lint`, complexity report in
  `complexity`); test folders `unit/`, `integration/`, `support/`, `fixtures/`.

## Files to Change
- `docs/agents/specs/architecture/repository-layout.md` — dockerfiles list, web root, stub, tests folders.
- `docs/agents/specs/architecture/database.md` — wait-for-db, test DB, idempotency rules.
- `docs/agents/specs/architecture/dependencies.md` — PHP 8.4.
- `docs/agents/specs/architecture/backend.md` — PHP 8.4.
- `docs/agents/specs/testing/backend.md` — composer scripts, linters, test folders.
