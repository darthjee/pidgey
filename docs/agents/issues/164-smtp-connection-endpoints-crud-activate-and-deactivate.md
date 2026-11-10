# Issue: SMTP connection endpoints: CRUD, activate and deactivate

## Description

Part of epic #149 (email integration). Implements the SMTP connection admin endpoints (list,
create, read, update, delete, activate, deactivate) as hardened by #153. The test email
endpoint is #166.

### Specs

- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md` (endpoints, field rules,
  edge cases, testable rules);
- `docs/agents/specs/api/endpoints/pidgey-admin.md` (access, guards, `:id`, operator log);
- `docs/agents/specs/api/conventions/validation.md#message-templates`;
- `docs/agents/specs/email/smtp-connections.md` (active connection, credentials);
- `docs/agents/specs/email/password-encryption.md#write-path`;
- `docs/agents/specs/organizations/data-model/smtp-connections.md#at-most-one-active-connection`;
- `docs/agents/specs/request-handler/routing.md#route-files`;
- implementation notes: `docs/agents/specs/implement-email/endpoints.md`,
  `implement-email/testing.md`.

### Dependencies

#160 (password encryption service, merged); #162 (logger and clock foundation, merged).
Agents: backend, security (review), product-owner (ticks the implement-email items).

## Problem

The SMTP connections table (`0008`, `0009`) and a partial `SmtpConnectionRepository` exist,
but no endpoint exposes them: an organization cannot create, edit, activate or deactivate a
connection, so nothing can be sent. No organization-area route file exists yet, and the
request validator only knows string fields, while these endpoints need integer, enum,
hostname and character rules with the shared message templates.

## Expected Behavior

Every endpoint behaves as `pidgey-admin/smtp-connections.md` specifies:

- `GET /smtp-connections.json` → `200 { "smtpConnections": [...] }`, ordered by `id`;
- `POST /smtp-connections.json` → `201 { "smtpConnection": ... }`, always inactive;
- `GET /smtp-connections/:id.json` → `200`;
- `PATCH /smtp-connections/:id.json` → `200`, reloaded item;
- `DELETE /smtp-connections/:id.json` → `204` (hard delete, active one included);
- `POST /smtp-connections/:id/activate.json` and `.../deactivate.json` → `200`, idempotent.

All go through the origin check (writes) and the gate; `404 SMTP connection not found` for an
unknown, out-of-range or foreign `:id`; `password` never in any response or log line;
`hasPassword` reflects `password IS NOT NULL`.

## Solution

### Routes

- New `source/config/routes/organization.php` (the area file named by
  `request-handler/routing.md#route-files`; organization and API-token routes join it later),
  required from `source/config/routes.php` before the frontend fallback. The
  `smtp-connections.php` name proposed in `implement-email/endpoints.md` is dropped.

### Validation

- Extend the shared `Pidgey\Http\Validator` / `FieldRules` (reused later by the organization
  and API-token endpoints) with the missing templates of `validation.md#message-templates`:
  integer (e.g. `$v->integer('port')->range(1, 65535)`), integer range, one of a set
  (`->oneOf(...)`), hostname or IP literal, no control characters, no line breaks.
- **Integer:** only a JSON integer is accepted; a float (`587.0`, `587.5`), a string
  (`"587"`) or a boolean is `<field> must be an integer`, and no further rule runs.
- **Host:** LDH hostname (labels 1–63, no leading or trailing hyphen, at most 253 in total),
  IPv4 literal, or IPv6 literal bare or in brackets. Rejected with
  `host must be a hostname or an IP address`: Unicode, a trailing dot
  (`smtp.example.com.`), an IPv6 zone id (`fe80::1%eth0`), and a dotted all-numeric value
  that is not a valid IPv4 (`999.1.1.1`).
- `Pidgey\Http\SmtpConnectionRules` declares the field set for create and update and applies
  the cross-field rules on the **merged state**: credential pairing, credentials require
  `tls` / `ssl`, and credential redirection (update only).
- **Change detection on `PATCH`:** a field is unchanged only when its trimmed value is
  identical to the stored one (exact, case-sensitive): `SMTP.example.com` against a stored
  `smtp.example.com` is a change and triggers credential redirection. A present non-empty
  `password` is always a change.

### Persistence

- Migration `0010_add_smtp_connections_active_unique.sql`: generated stored column
  `active_organization_id` = `IF(active, organization_id, NULL)` and unique index
  `uq_smtp_connections_active_organization_id`.
- `SmtpConnectionRepository`: add update (row lock `FOR UPDATE`, never writes `active`),
  delete, `activate` and `deactivate`; `OrganizationRepository::lockForUpdate`. The
  activate / deactivate steps live in the repositories, and the handler wraps them in
  `Transaction::run` (no `Pidgey\Email\ConnectionActivation`).
- Create: `INSERT` with `password = NULL`, encrypt with the new id's associated data, then
  `UPDATE`, all in one `Transaction`.

### Handlers and log

- Handlers `SmtpConnectionsHandler` (list, create), `SmtpConnectionHandler` (read, update,
  delete), `SmtpConnectionActivateHandler`, `SmtpConnectionDeactivateHandler`; serializer
  `SmtpConnectionPayload`.
- `Pidgey\Http\OperatorLog`: one `info` line per successful state change, context
  `userId`, `organizationId`, `connectionId`, `fields` (names only).

### Tests

- Every testable rule of `smtp-connections.md` except the test email ones (#166) and the
  `POST /v1/messages` `409 NO_SMTP_CONNECTION` follow-up (#165): deleting or deactivating
  the active connection is asserted as "no active connection".
- Canary password: never in a response body or a captured log line.
- Concurrency:
  - the lock-holder child-process script under `source/tests/fixtures/` (holds the
    organization lock) for concurrent `activate` and for `activate` against `deactivate`,
    with real waits and loose time bounds;
  - `Pidgey\Tests\Support\LockHookPdo` for `PATCH` racing `activate`, deterministic, in
    one process;
  - the unique index rejecting a second active row of the same organization.

### Scope boundaries

Not in scope: `POST /smtp-connections/:id/test.json`, migration `0011`, the test-email limit
(#166); `POST /v1/messages` (#165); the frontend (#167).

### Acceptance

- Every testable rule of the CRUD, activate and deactivate endpoints is covered.
- The matching items of `implement-email/endpoints.md` and `implement-email/testing.md`
  (lock-holder script) are ticked in the same PR.

## Benefits

- Organizations can manage their SMTP connections, the prerequisite for every send.
- The shared validation templates land once and serve the later admin endpoints.
