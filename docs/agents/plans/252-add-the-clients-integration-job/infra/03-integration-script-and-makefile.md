# Write integration.sh and the Makefile target

**`clients/scripts/integration.sh`** (`set -euo pipefail`, no argument, any working directory, runs inside the `clients` container):

1. Default `PIDGEY_INTEGRATION_URL=http://integration_app` and `PIDGEY_INTEGRATION_MAILPIT_URL=http://mailpit-plain:9025` when unset.
2. Wait (at most 60 s, 1 s steps, using `node -e` + `fetch` since the image may lack `curl`) for `GET $PIDGEY_INTEGRATION_URL/health.json` → `200` and `GET $PIDGEY_INTEGRATION_MAILPIT_URL/api/v1/info` → `200`; fail with a clear message otherwise.
3. Run `integration_seed.mjs` into a `mktemp` file (removed by `trap`), then `set -a; . "$file"; set +a`.
4. `export PIDGEY_INTEGRATION=required`.
5. For `nodejs` then `cli`, in a subshell: for `cli`, `rm -rf node_modules/pidgey-client` (same reason as `checks.sh`); `yarn install --frozen-lockfile --check-files`; `yarn test:integration`.

**`Makefile`**: target `clients-integration` (add to `.PHONY`):

```make
clients-integration:
	docker compose up -d mysql mailpit-plain
	docker compose run --rm integration_app sh -c "composer install --no-interaction --no-progress && composer db:prepare"
	docker compose up -d integration_app
	docker compose run --rm clients clients/scripts/integration.sh
```

(exact recipe may adjust, e.g. a `docker compose stop integration_app` at the end). `tests` gains `$(MAKE) clients-integration` after `clients-checks`.

## Files to Change
- `clients/scripts/integration.sh` — new, executable.
- `Makefile` — `clients-integration` target, `.PHONY`, `tests`.
