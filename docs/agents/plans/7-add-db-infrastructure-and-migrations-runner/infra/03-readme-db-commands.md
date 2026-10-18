# README DB commands

Document the database commands for humans.

- New "Database" section in `README.md`:
  - `make migrate` / `make db-reset` (development DB).
  - Inside `make dev` (`tests` service, `DB_NAME=pidgey_test`): `composer db:status`,
    `composer db:migrate`, and how to target the development DB from there:
    `DB_NAME=pidgey_development composer db:migrate`.
  - `composer tests` / `tests:integration` prepare `pidgey_test` automatically;
    `composer tests:unit` needs no database.
  - Migrations: `source/migrations/NNNN_description.sql`, exactly one statement per file,
    forward-only; never edit an applied file (checksums are verified).
  - Existing checkouts (one-time): the new grant only applies on a fresh MySQL volume — stop
    `mysql`, empty `docker_volumes/mysql_data` (keep the placeholder), start it again; or run the
    `GRANT` from `dockerfiles/mysql/initdb.d/01-grant-pidgey-dbs.sh` manually as root.
- Update the "First run" section to add `make migrate` after `composer install`.

## Files to Change
- `README.md` — Database section, first-run step.
