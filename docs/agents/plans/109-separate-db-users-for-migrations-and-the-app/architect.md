# Architect Plan: Separate DB users for migrations and the app

Main plan: [plan.md](plan.md)

## Shared contracts

- The shared `.env` gains optional `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD`, which `bin/migrate` uses during the deploy. `DB_USER` / `DB_PASSWORD` become the data-only app user once the owner has rolled out.
- The rollout order is in [plan.md](plan.md#shared-contracts).

## Implementation Steps

### Step 1 — Deployment docs

- `docs/agents/deployment/server.md`:
  - "Shared `.env`" key table: add a `DB_MIGRATE_USER`, `DB_MIGRATE_PASSWORD` row. They're optional; when set, they're used by `bin/migrate` (and create/drop-db), they must be set together (`bin/check-config`), and they fall back to `DB_USER`.
  - Host requirements: replace "The same user serves migrations and the app" with the two users and their grants.
- `docs/agents/deployment/known-gaps.md`: remove the "One database user" section, or turn it into a pointer to the rollout until the owner has switched production over. Prefer removal, since the code closes the gap.
- `docs/agents/deployment/operations.md`: add a short "Switching to separate DB users" procedure with the three ordered rollout steps, why the order matters, and how to verify each one (site health, `bin/migrate --status` on the next deploy).

## Files to Change

- `docs/agents/deployment/server.md`: `.env` keys, host DB requirement.
- `docs/agents/deployment/known-gaps.md`: drop "One database user" (and any anchor links to it, e.g. from `server.md`).
- `docs/agents/deployment/operations.md`: rollout procedure.

## Notes

- `server.md` links to `known-gaps.md#one-database-user`. Update or remove that link when the section goes.
