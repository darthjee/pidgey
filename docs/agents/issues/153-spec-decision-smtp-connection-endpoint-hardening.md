# Issue: Spec decision: SMTP connection endpoint hardening

## Description

Settle the **hardening and lifecycle decisions of the SMTP connection endpoints** for the
email integration (epic #149) in the specs, closing every open item of
`docs/agents/specs/implement-email/endpoints.md`.

### Context

`docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md` defines list, create,
read, update, delete, activate and test email, and lists several Pending decisions and Known
limitations; `docs/agents/specs/api/pending.md` tracks the cross-cutting ones. Any approved
user of the organization has equal access; the organization comes from the gate. The
production host is a shared Apache host with no proxy-level rate limiting.

## Problem

The endpoints leave server-side request forgery, DNS rebinding, credential redirection,
test-email abuse (rate, worker exhaustion, arbitrary recipient), deactivation, the "at most
one active" guarantee, concurrency, shared validation messages and the operator log
undecided, so the implementation issue cannot be written without guessing.

## Expected Behavior

### Decision 1 — SSRF and DNS rebinding

- New `.env` key `SMTP_ALLOW_PRIVATE_HOSTS` (boolean, default `false`); the development and
  test env files set it to `true` so Mailpit and internal relays stay usable.
- When `false`, these resolved addresses are denied: loopback, RFC 1918, `fc00::/7`,
  link-local (`169.254.0.0/16` including the metadata address, `fe80::/10`), `100.64.0.0/10`,
  unspecified, multicast and reserved ranges (IPv4 and IPv6, including IPv4-mapped IPv6).
- Checked **at send time only** (test email and `POST /v1/messages`), after resolution: the
  host is resolved **once**; if any resolved address is denied the send is refused with
  `409 SMTP_HOST_NOT_ALLOWED` — `The SMTP host resolves to an address that is not allowed`,
  and the server is never contacted. An IP literal is checked as is.
- The transport connects to the **checked IP** (IPv4 preferred; no fallback to other
  addresses in v1); TLS peer name and SNI stay `host` (IP-literal rules of #152 unchanged).
- Ports are not restricted. The reachability oracle on public hosts stays a known limitation.

### Decision 2 — Credential redirection

- Adopted: when the merged state still has credentials and `host`, `port` or `encryption`
  changes, the same `PATCH` must send a non-empty `password` or clear both credentials.
- Otherwise `400 VALIDATION_FAILED` —
  `password must be sent again when host, port or encryption changes`.
- Changing `name`, `fromAddress` or `username` alone does not require it.

### Decision 3 — Test-email rate limit

- Scope: **per organization**, stored in a new table `smtp_test_limits` (one row per
  organization, upserted): window start, attempt count, `busy_until`.
- Fixed window: `SMTP_TEST_MAX_ATTEMPTS` (default `10`) per `SMTP_TEST_WINDOW` seconds
  (default `3600`). Every attempt that passes validation counts, whatever its outcome.
- Worker exhaustion: at most **one test in flight per organization**, via a lease
  `busy_until = now + SMTP_TOTAL_TIMEOUT + margin` taken in a short locked transaction before
  the send and cleared after it; no database lock is held during the send.
- Answer: `429 TOO_MANY_REQUESTS` with `Retry-After` (seconds) — `Too many test emails; try
  again later` (window) or `A test email is already in progress` (lease).
- Arbitrary `to`: `to` must be the email of a user of the caller's organization (default
  still the caller's own); otherwise `400 VALIDATION_FAILED` —
  `to must be the email of a user of the organization`.

### Decision 4 — `deactivate` endpoint

- Added: `POST /smtp-connections/:id/deactivate.json`, no body; in one transaction lock the
  organization row, load the target (unknown → `404`), set `active = false`.
- `200 { "smtpConnection": … }`, idempotent; operator log `smtp-connection-deactivate`.
- Deleting is no longer the only way to have no active connection.

### Decision 5 — Database-level "at most one active"

- New migration `0010`: generated stored column
  `active_organization_id = IF(active, organization_id, NULL)` with a unique index.
- The locked transaction of activate stays (deactivate others first, then activate); the
  index is defense in depth. No production rows exist, so no backfill concern.

### Decision 6 — Concurrency

- Test email uses the **snapshot** read at its start; edits or deletes during the send do not
  affect it, the outcome is still answered, and the log line carries the connection id
  without re-checking the row.
- `PATCH` locks the connection row (`SELECT … FOR UPDATE`) for read-merge-validate-write; it
  does not take the organization lock and never writes `active`, so it cannot overwrite an
  activation.

### Decision 7 — Spec inconsistency

- `organizations/data-model/smtp-connections.md` gains the new column/index and its Pending
  decisions match; the summary in `api/endpoints/pidgey-admin.md` lists what remains.

### Decision 8 — Shared message templates

- Adopted in `api/conventions/validation.md`: `<field> must be an integer`,
  `<field> must be between <min> and <max>`, `<field> must be a hostname or an IP address`,
  `<field> must not contain control characters`, `<field> must not contain line breaks`.

### Decision 9 — Operator log

- `Pidgey\Infra\LoggerFactory` builds a Monolog logger with an `ErrorLogHandler` (same
  destination as today's `error_log()`).
- Line: the action name as message plus JSON context `userId`, `organizationId`,
  `connectionId`, `fields` (names only); level `info`.
- New `.env` key `LOG_LEVEL` (default `info`), closing the log-level pending item of
  `architecture/configuration.md`.

## Solution

### Where to write

- `api/endpoints/pidgey-admin/smtp-connections.md` (field rules, deactivate, test email,
  errors, known limitations, edge cases, testable rules, pending decisions),
  `api/endpoints/pidgey-admin.md` (route table, operator log actions, pending summary),
  `api/pending.md` (SSRF, credential redirection, test-email part of rate limits),
  `email/smtp-connections.md`, `email/send-flow.md` and `email/failures.md`
  (`SMTP_HOST_NOT_ALLOWED`, resolve-once connect), `api/conventions/errors.md` (`429`),
  `api/conventions/validation.md` (templates),
  `organizations/data-model/smtp-connections.md` and a new
  `organizations/data-model/smtp-test-limits.md` (+ `data-model.md` index),
  `architecture/configuration.md` (new keys, `LOG_LEVEL`).
- `implement-email/endpoints.md`: close every open item and list the resulting work
  (handlers, routes, migrations `0010`/`0011`, `LoggerFactory`, configuration).
- Deployment docs (architect): `deployment/server.md` / `variables.md` for the new keys,
  `known-gaps.md` (SSRF stays for public hosts/ports).

### Backward compatibility

- No production SMTP rows exist; migrations are additive.
- Existing stored rows keep working; the redirection rule only applies on `PATCH`.

### Out of scope

Code; encryption, transport and test-infrastructure decisions (other #149 issues); global
API rate limits; the admin UI for deactivate (frontend follow-up).

### Acceptance

- Each decision above is recorded in the permanent specs and none stays open in
  `implement-email/endpoints.md`.

## Benefits

Closes the SSRF, credential-redirection and test-email abuse gaps before the endpoints are
built, and gives the implementation issue a complete, unambiguous contract.
