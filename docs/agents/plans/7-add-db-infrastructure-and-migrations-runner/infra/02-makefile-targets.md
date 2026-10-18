# Makefile targets

Add the development-database targets. Both run through the `app` service so they always act on
`pidgey_development` (the `tests` service, used by `make dev`, points at `pidgey_test`).

- `migrate`: `docker compose run --rm app composer db:prepare` (wait, create if missing,
  migrate).
- `db-reset`: `docker compose run --rm app sh -c "composer db:drop && composer db:prepare"`.
- Add both to `.PHONY`.

## Files to Change
- `Makefile` — `migrate` and `db-reset` targets, `.PHONY`.
