# Issue: Test email endpoint and rate limit

## Description

Part of epic #149 (email integration). Implements `POST /smtp-connections/:id/test.json`,
which sends a fixed test email through a connection.

### Specs

- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson`;
- `docs/agents/specs/organizations/data-model/smtp-test-limits.md`;
- `docs/agents/specs/architecture/configuration.md#smtp-transport`
  (`SMTP_TEST_MAX_ATTEMPTS`, `SMTP_TEST_WINDOW`);
- implementation notes: `docs/agents/specs/implement-email/endpoints.md` (test email,
  test-email limit).

## Problem

Organization users cannot check an SMTP connection before activating or relying on it: there is no test-email endpoint yet. The endpoint opens an outbound SMTP connection and holds a worker for up to `SMTP_TOTAL_TIMEOUT`, and the production host has no proxy-level rate limiting, so without its own limit it could exhaust workers; without a recipient restriction it could relay mail to arbitrary addresses.

## Expected Behavior

### Scope

In scope:

- `Pidgey\Http\Handler\SmtpConnectionTestHandler` and its route in
  `source/config/routes/organization.php`.
- `to` validation: format, trimming, at most 255 characters, no CR or LF; must be the
  `users.email` of a user of the organization; absent or `null` → the caller's own email.
- `Pidgey\Email\TestEmail`: the fixed subject and body.
- Migration `0011_create_smtp_test_limits.sql`.
- The test-email limit (window, lease, `Retry-After`, `429`). Proposed names, final split
  left to the plan: `Pidgey\Repository\SmtpTestLimitRepository` (row upsert, lock, update,
  lease release) and `Pidgey\Email\TestEmailLimit` (window and lease rules).
- `ConfigValidator`: `SMTP_TEST_MAX_ATTEMPTS` (count) and `SMTP_TEST_WINDOW` (duration), with
  their defaults.
- The `smtp-connection-test` operator log line, once per attempt that reached the send.
- The send goes through the existing `SendFlow` (#165) with `SendActor::user()`. The send-time
  checks (`409 SMTP_INSECURE_CREDENTIALS`, `SMTP_HOST_NOT_ALLOWED`,
  `SMTP_PASSWORD_UNREADABLE`) already exist there; this issue wires and tests them for the
  test email.
- Tests: the test-email testable rules, the limit with a frozen clock, one integration test
  with `SMTP_ALLOW_PRIVATE_HOSTS=false` against `127.0.0.1` expecting `409`, the canary
  password.

Out of scope:

- The frontend and the API spec fixture examples (#167).
- Any change to `SendFlow`, `HostPolicy` or the transport; a gap found there becomes its own
  issue.
- Rate limiting `POST /v1/messages`; proxy-level limits.
- Cleanup or expiry of `smtp_test_limits` rows (never deleted).
- Deployment docs (`deployment/variables.md` already lists the variables).

### Edge cases

Decided (spec clarifications made by the product-owner in the same PR, in
`api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidtestjson`):

- **Case of `to`:** matched with the case-insensitive collation (`BOB@Example.com` matches
  `bob@example.com`); the email is sent to the **stored** `users.email`, never to the typed
  value.
- **User status:** only `approved` users of the organization qualify; a `pending` or
  `denied` user answers the same `400` — `to must be the email of a user of the
  organization` (no user enumeration). The caller's own default address is unaffected.
- **Empty `to`:** `""` or whitespace only → `400` — `to should not be empty` (rule 4 of
  `api/conventions/validation.md`), no fallback to the caller's email; only absent or `null`
  falls back.

Implementation note: the first attempt of an organization creates the row with
`INSERT … ON DUPLICATE KEY UPDATE`, followed by `SELECT … FOR UPDATE` in the same short
transaction, so two concurrent first attempts never duplicate it or fail.

Already specified, to be covered by tests: the window boundary
(`window_started_at + SMTP_TEST_WINDOW <= now` starts a new window), the lease checked before
the window, refused attempts not counting, `Retry-After` rounded up and at least `1`, an
inactive connection being testable, the row snapshot, and an expired lease from a dead
worker.

### Clock

- The limit uses `Pidgey\Infra\WallClock`, as every other repository that stores times
  (`RefreshTokenRepository`, `UserRepository`, …): `now()` is read once per request and passed
  to the repository as `DateTimeImmutable $now`; `busy_until`
  (`now + SMTP_TOTAL_TIMEOUT + 5`) and `Retry-After` are computed in PHP from that value.
- No SQL `NOW()` / `UTC_TIMESTAMP()` for the window or the lease (`created_at` /
  `updated_at` defaults unchanged). `DATETIME` values are UTC.
- The monotonic `Pidgey\Infra\Clock` stays confined to the SMTP session deadline inside
  `SendFlow`.
- Spec fix in the same PR: `organizations/data-model/smtp-test-limits.md` says "Times come
  from `Pidgey\Infra\Clock`"; it becomes `Pidgey\Infra\WallClock` (matching
  `implement-email/endpoints.md`).
- Tests drive the window and the lease with a frozen `WallClock`.

### Lease release

- **`finally`:** the send runs in `try`, the release in `finally`, so the lease is cleared on
  every outcome: `201`, a `409` send-time check, any `SMTP_*` failure, and any unexpected
  `Throwable` (answered `INTERNAL_ERROR`).
- **Compare-and-clear:** a single autocommit
  `UPDATE smtp_test_limits SET busy_until = NULL WHERE organization_id = ? AND busy_until = ?`
  bound to the `busy_until` this request set, so a worker that overran its lease never clears
  a newer lease taken by another request.
- **A failed release never changes the response:** its error is caught inside `finally` (it
  must not replace the send outcome; the email may already be sent) and logged at `warning`
  with `organizationId` and `connectionId`, the exception class only. The lease then
  self-expires at `busy_until`.
- **Fatal errors** (PHP fatal, `max_execution_time`, killed worker) skip `finally` and rely on
  the self-expiring lease, as the spec states; no extra handling.
- Tests: release after `201`, `409`, an `SMTP_*` failure and an unexpected exception; a stale
  release leaves a newer lease untouched; a failing release still returns the original
  outcome and logs the warning.

### Logging

Per `api/endpoints/pidgey-admin.md#operator-log` and `email/send-flow.md#attempt-log`:

- **Outcome parameter:** `Pidgey\Http\OperatorLog::record` gains an optional
  `?string $outcome = null`, written to the context as `outcome` only when set; other actions
  are unchanged.
- **Two lines per test, in order:** first `SendFlow`'s `smtp-send-attempt` (`info` / `warning`,
  with recipient, step, reply), then the handler's `smtp-connection-test` at `info` (even on a
  failed send) with `userId`, `organizationId`, `connectionId`, `outcome` (`sent` or the
  `SMTP_*` code), never the recipient.
- **Refused requests** (`400`, `404`, `429`) write no line.
- **Unexpected exception during the send (`500`):** no operator line; the existing `500` path
  logs the error class, route, user and organization, and the lease is still released. So
  `outcome` is always `sent` or an `SMTP_*` code, never `INTERNAL_ERROR`.
- Tests: both lines and their order; the canary password and the typed `to` never appear in
  the operator line; the recipient appears only in `smtp-send-attempt`, as the stored
  `users.email`.

### Coordination with #167

#167 (frontend alignment) adds example responses to the same
`api/endpoints/pidgey-admin/smtp-connections.md`.

- **No merge order required:** #166 does not depend on #167; #167's manual e2e check is a
  pre-merge item waiting for #166. The second PR to merge rebases.
- **Disjoint edits:** #166 changes only the `to` field row and adds the status / empty-string
  sentences in the `test.json` section; it never adds or changes example JSON blocks or
  fixture references (owned by #167).
- **Bodies match the fixtures:** backend tests assert the exact spec strings
  (`Too many test emails; try again later`, `A test email is already in progress`,
  `to must be the email of a user of the organization`, `to should not be empty`) and the
  `201` shape `{status, smtp: {code, message}}`.
- **No frontend change expected:** the new `400` messages arrive as ordinary
  `error.details` on `to`, which the test modal already displays.

### Acceptance

- Every testable rule of the test email endpoint is covered.
- The matching items of `implement-email/endpoints.md` are ticked in the same PR: items 1
  and 2 (test email handler, `to` organization-user rule), 3 (migration `0011`), 5
  (test-email limit) and the `smtp-connection-test` entry of item 6.

## Solution

### Alternatives considered

The design is settled in the specs; these were rejected and should not be reopened by the
plan:

- **Limit storage:** a MySQL row per organization (`smtp_test_limits`). APCu / in-memory is
  not shared across workers or hosts; Redis would be a new dependency.
- **Window shape:** fixed window. A sliding window or token bucket adds state and tests for
  little gain on a low-volume admin action.
- **One test in flight:** a `busy_until` lease column. `GET_LOCK()` or holding
  `FOR UPDATE` during the send would keep a DB connection or lock for up to
  `SMTP_TOTAL_TIMEOUT` (forbidden by the spec); the lease self-expires if a worker dies.
- **Limit scope:** per organization, not per user, since the threat is worker exhaustion.
- **Recipient:** must be a user of the organization. Any address would make the endpoint a
  spam relay; a verified-address list would be a new feature.
- **Execution:** synchronous. There is no queue, and the UI needs the SMTP reply right away.

### Dependencies

#164 (SMTP connection endpoints); #165 (send flow). Agents: backend, security, product-owner (spec clarifications).

## Benefits

- Connections can be tested (active or not) with real SMTP feedback before activation.
- Worker exhaustion and open-relay abuse are prevented by the per-organization window, the single in-flight lease and the organization-user recipient rule.
- Unblocks the manual end-to-end check of #167.
