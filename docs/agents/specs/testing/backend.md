# Backend Testing

Purpose: define backend test suites and quality tooling.

- **PHPUnit**, two suites:
  - `unit` (`source/tests/unit/`): no database, no network.
  - `integration` (`source/tests/integration/`): run against the dockerized MySQL
    (dedicated test database, migrations applied) and Mailpit (real SMTP sends,
    asserted through Mailpit's API — see [email/testing.md](../email/testing.md)).
- Coverage with pcov.

Composer scripts:

| Script | Action |
| --- | --- |
| `tests` | All PHPUnit suites. |
| `tests:unit` | Unit suite. |
| `tests:integration` | Integration suite. |
| `coverage` | PHPUnit with coverage report. |
| `lint` | phpcs with slevomat/coding-standard (and phpmd). |
| `docs` | phpdocumentor. |
