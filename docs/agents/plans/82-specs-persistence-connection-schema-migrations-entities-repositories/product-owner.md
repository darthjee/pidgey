# Product Owner Plan: Specs: persistence — connection, schema migrations, entities, repositories

Main plan: [plan.md](plan.md)

## Overview

Add the `persistence` spec area describing how application code uses the database (connection, schema migrations, entities, repositories, testing), index it, and link it from the architecture specs. No code changes.

## Context

The database infrastructure already exists in `Pidgey\Infra` and the tables are specified in `organizations/data-model/`, but no spec covers entities, repositories, transactions, initial migrations or repository tests. These specs unblock the follow-up implementation epic.

## Implementation Steps

### Step 1 — Write the persistence spec area (done in `24f9314`)

Create `docs/agents/specs/persistence.md` and the five sub-specs under `docs/agents/specs/persistence/`, index the area in `docs/agents/specs.md`, and link it from `architecture/database.md` and `architecture/backend.md`. Already committed on `issue-82`; do not rewrite.

### Step 2 — Verify coverage against the issue

Check every scope bullet, decision and out-of-scope item from the issue against the committed files, check that all relative links and anchors resolve, and check consistency with `organizations/data-model.md` (8 tables, FK order, enum values). Verified: fully covered, no broken links, pending items (SMTP password encryption, one-active-SMTP-connection constraint) left pending, no code files touched.

## Files to Change

- `docs/agents/specs/persistence.md` — new area index.
- `docs/agents/specs/persistence/connection.md` — `PdoFactory` options, per-request PDO, `Transaction::run()`.
- `docs/agents/specs/persistence/schema-migrations.md` — migration files 0001–0008, naming, engine/charset.
- `docs/agents/specs/persistence/entities.md` — `final readonly` entities with `fromRow()`, `Row` helper, enums.
- `docs/agents/specs/persistence/repositories.md` — repository conventions and minimal methods.
- `docs/agents/specs/persistence/testing.md` — rollback isolation, `DatabaseCleaner`, `Fixtures`, `SchemaTest`.
- `docs/agents/specs.md`, `docs/agents/specs/architecture/database.md`, `docs/agents/specs/architecture/backend.md` — index and links.

## Notes

- Docs only; no CI job exercises these files beyond generic checks.
