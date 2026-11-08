# SMTP Test Limits Table

Purpose: define the `smtp_test_limits` table that rate-limits the SMTP connection test email
per organization.

The test email opens an outbound SMTP connection and holds a worker for up to
`SMTP_TOTAL_TIMEOUT`, and the production host has no proxy-level rate limiting, so each
organization gets a fixed attempt window and a single in-flight lease. The rules are defined
in
[api/endpoints/pidgey-admin/smtp-connections.md](../../api/endpoints/pidgey-admin/smtp-connections.md#test-email-limit);
the settings (`SMTP_TEST_MAX_ATTEMPTS`, `SMTP_TEST_WINDOW`, `SMTP_TOTAL_TIMEOUT`) in
[architecture/configuration.md](../../architecture/configuration.md#smtp-transport).
Conventions are in [conventions.md](conventions.md).

## `smtp_test_limits`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | BIGINT | no | auto | PK |
| `organization_id` | BIGINT | no | | FK → `organizations.id`; **unique** (one row per organization, upserted) |
| `window_started_at` | DATETIME | no | | Start of the current fixed window |
| `attempts` | INT | no | `0` | Test-email attempts counted in the current window |
| `busy_until` | DATETIME | yes | null | Lease: a test email of the organization is in flight until then; `NULL` when none |
| `created_at` | DATETIME | no | now | |
| `updated_at` | DATETIME | no | now, on update | |

Indexes: unique `organization_id` (`uq_smtp_test_limits_organization_id`).

## Usage

- The row is created on the organization's first test email (upsert) and never deleted by
  the application.
- Each test email locks the row (`SELECT … FOR UPDATE`) in a **short transaction** only: it
  checks the lease (`busy_until > now`) and the window, then either refuses with `429` (row
  unchanged) or, when `window_started_at + SMTP_TEST_WINDOW <= now`, starts a new window
  (`window_started_at = now`, `attempts = 0`), increments `attempts`, sets
  `busy_until = now + SMTP_TOTAL_TIMEOUT + 5` seconds, and commits.
- **No lock is held during the send.** After the send, whatever its outcome,
  `busy_until` is set back to `NULL`.
- A worker that dies during the send leaves `busy_until` set; the lease expires on its own
  once `busy_until` is in the past.
- Times come from `Pidgey\Infra\Clock`, so tests can drive the window and the lease.

## Migrations

- `0011_create_smtp_test_limits.sql` creates the table (one `CREATE TABLE`, see
  [persistence/schema-migrations.md](../../persistence/schema-migrations.md#rules)).

## Pending decisions

None.
