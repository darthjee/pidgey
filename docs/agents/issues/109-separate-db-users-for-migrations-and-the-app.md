# Issue: Separate DB users for migrations and the app

## Description

Production uses one MySQL user, with DDL rights, for both migrations and the web app. This is a known gap from epic #95 (`specs/deployment/security.md`, "Known gaps"). The host does support a second user on the same database, and the owner has already created one. The app cannot use it yet, because every entry point reads the same `DB_USER`/`DB_PASSWORD` through `DatabaseConfig::fromEnv()`. That includes the web app (`Container`) and every `bin/` database command (`DatabaseCommand::fromEnvironment()`, which shares one config loader).

## Expected Behavior

- **Schema/admin commands** (`bin/migrate`, `bin/create-db`, `bin/drop-db`) connect as the migration user. It has Select, Insert, Update, Delete, Create, Drop, Index and Alter on the app database.
- **`bin/wait-for-db`** keeps connecting as the regular app user (`DB_USER`). It only checks that the server accepts connections, so it needs no extra rights.
- **The web app** (`public/index.php`) connects as the app user. It has Select, Insert, Update and Delete only.
- Both users' settings live in the shared `.env`. Nothing about them is committed.
- Docker, CI and tests keep working with a single user, without any change.

## Solution

- Add optional `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD`. `DB_HOST`, `DB_PORT` and `DB_NAME` are shared.
  - When they are set, `bin/migrate`, `bin/create-db` and `bin/drop-db` use them. Otherwise those commands fall back to `DB_USER` / `DB_PASSWORD`.
  - `bin/wait-for-db` and the web app always use `DB_USER` / `DB_PASSWORD`.
  - `DatabaseCommand` therefore needs two config loaders, or a per-command choice of loader, instead of the single shared one it has today.
- `bin/check-config` validates the pairing: if one of `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` is set, both must be set. If neither is set, there is no error (the fallback applies). It never prints their values.
- Docs:
  - `specs/architecture/configuration.md`: add the new env keys.
  - The deployment docs (#101), for example `deployment/server.md`: add the shared `.env` keys and the rollout.
  - `specs/deployment/security.md`: remove "Database user" from Known gaps and record the two-user split, with each user's grants, as a security decision. Note that switching production over is a manual step done by the owner.
- Rollout, done by hand by the owner after the release that ships this:
  1. Add `DB_MIGRATE_*` with the existing user's credentials.
  2. Switch `DB_USER` / `DB_PASSWORD` to the new data-only user.
  3. Verify that the site and the next deploy's `bin/migrate` both work.

  The order matters. If `DB_USER` were switched before `DB_MIGRATE_*` exists, the next deploy's migrations would fail.

## Notes

- Raised while refining #101.
- Owner: backend. Infra updates the docs, and security reviews the change.
