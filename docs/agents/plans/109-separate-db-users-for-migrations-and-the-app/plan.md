# Plan: Separate DB users for migrations and the app

Issue: [109-separate-db-users-for-migrations-and-the-app.md](../../issues/109-separate-db-users-for-migrations-and-the-app.md)

## Overview

Add optional `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` settings. When they are set, the schema/admin commands (`bin/migrate`, `bin/create-db`, `bin/drop-db`) connect as that user; otherwise they fall back to `DB_USER` / `DB_PASSWORD`. `bin/wait-for-db` and the web app always use `DB_USER`. `bin/check-config` enforces that the two new keys are set together. Specs and deployment docs record the new keys, the two-user split as a security decision, and the manual production rollout.

## Agents involved

- [backend](backend.md)
- [product-owner](product-owner.md)
- architect: deployment docs, written directly by the coordinator (see [architect.md](architect.md))
- security: read-only review of the backend change once it lands (no plan file)

## Shared contracts

- **New env keys** (both optional, no default):
  - `DB_MIGRATE_USER`: database user for `bin/migrate`, `bin/create-db`, `bin/drop-db`.
  - `DB_MIGRATE_PASSWORD`: its password.
- **Fallback:** when neither key is set, those commands use `DB_USER` / `DB_PASSWORD`, so the behavior is identical to today. `DB_HOST`, `DB_PORT` and `DB_NAME` are always shared.
- **Users per entry point:**
  - Migration user (or fallback): `bin/migrate` (including `--status`), `bin/create-db`, `bin/drop-db`.
  - App user (`DB_USER` / `DB_PASSWORD`), always: `bin/wait-for-db`, `public/index.php` (`Container`).
- **`bin/check-config` failure lines** (exact text, value never printed; empty counts as unset, like every other key):
  - `DB_MIGRATE_PASSWORD: missing` when `DB_MIGRATE_USER` is set and `DB_MIGRATE_PASSWORD` is not.
  - `DB_MIGRATE_USER: missing` when `DB_MIGRATE_PASSWORD` is set and `DB_MIGRATE_USER` is not.
  - Neither set → no failure.
- **Grants:**
  - Migration user: `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `INDEX`, `ALTER`, `REFERENCES` on the app database. `REFERENCES` is needed because migrations create foreign keys (e.g. `0002_create_users.sql`).
  - App user: `SELECT`, `INSERT`, `UPDATE`, `DELETE` only.
- **Rollout (manual, owner, after the release ships):**
  1. Add `DB_MIGRATE_*` with the existing user's credentials.
  2. Switch `DB_USER` / `DB_PASSWORD` to the data-only user.
  3. Verify the site and the next deploy's `bin/migrate`.

  The order matters: switching `DB_USER` first would make the next deploy's migrations fail.

## Notes

- The issue's grant list for the migration user omits `REFERENCES`. The current user already has it (`deployment/server.md`), and migrations need it for foreign keys, so the docs list it.
- Docker (`.env`), CI (`.circleci/config.yml`) and tests set no `DB_MIGRATE_*` and rely on the fallback. No infra change is needed.
