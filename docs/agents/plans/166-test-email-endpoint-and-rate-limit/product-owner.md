# Product-owner Plan: Test email endpoint and rate limit

Main plan: [plan.md](plan.md)

## Shared contracts

The `to` rules, the exact strings and the `WallClock` decision from [plan.md](plan.md#shared-contracts).
Coordination with #167: edit only the `to` row and add sentences in the `test.json`
section; never add or change example JSON blocks or fixture references, which #167 owns.

## Implementation Steps

### Step 1 — Spec clarifications

- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`, section
  `POST /smtp-connections/:id/test.json`:
  - in the `to` row, say the field is trimmed and that `""` or whitespace only answers
    `to should not be empty` (no fallback to the caller's email); only absent or `null`
    falls back;
  - say the organization-user rule matches `approved` users only, case-insensitively,
    that `pending` and `denied` users get the same message, and that the email is sent to
    the stored `users.email`, never to the typed value.
- `docs/agents/specs/organizations/data-model/smtp-test-limits.md`, Usage: replace
  "Times come from `Pidgey\Infra\Clock`" with `Pidgey\Infra\WallClock`, and add that the
  first attempt upserts the row (`INSERT … ON DUPLICATE KEY UPDATE`) before
  `SELECT … FOR UPDATE` in the same transaction, and that the lease is cleared by a
  compare-and-clear on the `busy_until` value the request set.

### Step 2 — Tick the implementation notes

In `docs/agents/specs/implement-email/endpoints.md`, "Implementation work", tick:

- item 1, the test email handler (#166);
- item 2, the organization-user rule of the test email `to` (#166);
- item 3, `0011_create_smtp_test_limits.sql` (#166);
- item 5, the test-email limit (mark it `[x]` with `(#166)`);
- item 6, the `smtp-connection-test` operator log line (#166).

Do it in the same PR, once the backend work is in.

## Files to Change

- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`: the `to` clarifications.
- `docs/agents/specs/organizations/data-model/smtp-test-limits.md`: `WallClock`, upsert, compare-and-clear.
- `docs/agents/specs/implement-email/endpoints.md`: the ticks.

## Notes

- No `Pending decisions` are added; every point was settled on the issue.
