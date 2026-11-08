# Data model: active index and test limits table

Decisions 5 and 7 (data-model part).

- `organizations/data-model/smtp-connections.md`: add the generated stored column
  `active_organization_id` BIGINT NULL = `IF(active, organization_id, NULL)` and a unique
  index on it; migration `0010_add_smtp_connections_active_unique.sql`. Note that activate
  still deactivates the others before activating the target, so the index is never hit in
  normal operation (a violation is a `500`). Keep "Pending decisions: None", now accurate.
- New `organizations/data-model/smtp-test-limits.md`: table `smtp_test_limits` — `id`,
  `organization_id` (FK, **unique**), `window_started_at` DATETIME, `attempts` INT default 0,
  `busy_until` DATETIME NULL, `created_at`, `updated_at`; migration
  `0011_create_smtp_test_limits.sql`; usage per step 03. Add it to `organizations/data-model.md`.
- `email/smtp-connections.md` and the endpoint spec: drop the pending database-constraint
  bullets.
- `persistence/schema-migrations.md`: update the cross-reference if it mentions the
  constraint as pending.

## Files to Change
- `docs/agents/specs/organizations/data-model/smtp-connections.md`
- `docs/agents/specs/organizations/data-model/smtp-test-limits.md` (new)
- `docs/agents/specs/organizations/data-model.md`
- `docs/agents/specs/email/smtp-connections.md`
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`
- `docs/agents/specs/persistence/schema-migrations.md`
