# Backend Testing

Purpose: define backend test suites and quality tooling.

- **PHPUnit**, two suites:
  - `unit` (`source/tests/unit/`): no database, no network.
  - `integration` (`source/tests/integration/`): run against the dockerized MySQL
    (dedicated test database, migrations applied) and Mailpit (real SMTP sends,
    asserted through Mailpit's API — see [email/testing.md](../email/testing.md)).
- Coverage with pcov.

Test folders under `source/tests/`:

| Folder | Content |
| --- | --- |
| `unit/` | Unit suite. |
| `integration/` | Integration suite. |
| `support/` | Shared test helpers. |
| `fixtures/` | Static test data. |

Composer scripts:

| Script | Action |
| --- | --- |
| `tests` | All PHPUnit suites. |
| `tests:unit` | Unit suite. |
| `tests:integration` | Integration suite. |
| `coverage` | PHPUnit with coverage report (pcov, Clover output used by CI). |
| `lint` | phpcs, then the phpmd unused-code gate. |
| `lint:fix` | phpcbf: auto-fix what phpcs can fix. |
| `docs` | phpdocumentor (not installed via Composer; phar or `phpdoc/phpdoc` image, decided in #6 — see [dependencies](../architecture/dependencies.md)). |
| `complexity` | phpmd complexity report (informational, not a gate). |

Linters:

- **phpcs**: PSR-12 plus slevomat/coding-standard rules; PHPDoc is required outside tests.
- **phpmd**: the unused-code ruleset is a gate in `lint`; the complexity rules produce a
  report through `complexity`.
