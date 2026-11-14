# Plan: Test email endpoint and rate limit

Issue: [166-test-email-endpoint-and-rate-limit.md](../../issues/166-test-email-endpoint-and-rate-limit.md)

## Overview

Add `POST /smtp-connections/:id/test.json`. It sends the fixed test email through any
connection of the caller's organization, using the existing `SendFlow` (#165) with
`SendActor::user()`. The recipient must be an `approved` user of the organization, and the
email goes to the stored `users.email`. A per-organization fixed window and a single
in-flight lease protect the endpoint. Both are stored in `smtp_test_limits` (migration
`0011`) and timed by `WallClock`. A refused attempt answers `429` with `Retry-After`. The
product-owner makes the spec clarifications agreed on the issue (user status, empty `to`,
`Clock` → `WallClock`) and ticks the implementation-notes items.

## Agents involved

- [product-owner](product-owner.md)
- [backend](backend.md)

## Shared contracts

### Route and request

- `POST /smtp-connections/:id/test.json`, gated (`Gate::class`), registered in
  `source/config/routes/organization.php` next to the activate and deactivate routes.
- Body `{ "to": "<email>" }`; `to` is optional.

### `to` rules, in order (each failure is `400 VALIDATION_FAILED`, one message in `error.details`)

1. Absent or `null` → the caller's own `users.email` (no further rule).
2. Trimmed. `""` or whitespace only → `to should not be empty`.
3. The existing format templates: a valid email address, at most 255 characters, no CR or LF
   (same `FieldRules` chain and messages as other email fields).
4. Organization-user rule: no `approved` user of the caller's organization has this email
   (case-insensitive collation) → `to must be the email of a user of the organization`. The
   same message covers `pending` and `denied` users and unknown addresses.
5. The recipient actually used is the **stored** `users.email`, never the typed value.

### Order of checks

Guards, then `to` (`400`), then the connection lookup in the organization (`404 NOT_FOUND`,
`SMTP connection not found`), then the limit (`429`), then the send-time checks and the
send.

### Responses (exact strings)

- `201`: `{ "status": "sent", "smtp": { "code": <int>, "message": "<cleaned reply>" } }`.
- `429 TOO_MANY_REQUESTS`, `Retry-After: <seconds>` header (whole seconds, rounded up, at
  least `1`):
  - lease held: `A test email is already in progress` (checked first);
  - window full: `Too many test emails; try again later`.
- Send failures: `SmtpFailureException::fromOutcome($outcome, $port, $encryption)`, unchanged
  (the `409` send-time codes and the `SMTP_*` `502`/`503`/`504` codes).

### `smtp_test_limits` (migration `0011_create_smtp_test_limits.sql`)

`id` BIGINT PK auto; `organization_id` BIGINT FK → `organizations.id`, unique
`uq_smtp_test_limits_organization_id`; `window_started_at` DATETIME not null; `attempts` INT
not null default `0`; `busy_until` DATETIME null; `created_at` / `updated_at` as in the
other tables. One `CREATE TABLE` statement. Times are UTC, computed in PHP from
`WallClock::now()` (no SQL `NOW()` for the window or the lease).

### Configuration

- `SMTP_TEST_MAX_ATTEMPTS`, default `10`, positive integer (a count).
- `SMTP_TEST_WINDOW`, default `3600`, positive integer seconds (a duration).
- Lease length: `SMTP_TOTAL_TIMEOUT + 5` seconds.

### Operator log

`smtp-connection-test` at `info`, with context `userId`, `organizationId`, `connectionId` and
`outcome` (`sent` or the `SMTP_*` / send-time code). It is written after `SendFlow`'s
`smtp-send-attempt` line, only for attempts that reached the send, and never for
`400`/`404`/`429`/`500`. `OperatorLog::record` gains an optional trailing
`?string $outcome = null`.
