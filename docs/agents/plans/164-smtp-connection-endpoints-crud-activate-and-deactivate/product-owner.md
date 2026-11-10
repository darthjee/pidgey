# Product Owner Plan: SMTP connection endpoints: CRUD, activate and deactivate

Main plan: [plan.md](plan.md)

## Shared contracts

Relies on the backend producing the names in [plan.md#shared-contracts](plan.md#shared-contracts);
records the decisions below in the permanent specs.

## Implementation Steps

### Step 1 — Record the decisions

- `api/endpoints/pidgey-admin/smtp-connections.md#field-rules`: `port` rejects floats
  (`587.0`) and booleans; `host` rejects a trailing dot, an IPv6 zone id and a dotted
  all-numeric value that is not a valid IPv4; "not a change" means the trimmed value is
  identical to the stored one (case-sensitive), so a case change of `host` triggers credential
  redirection. Add the matching Edge cases lines.
- `api/conventions/validation.md#message-templates`: note that the integer template also
  covers floats with no fraction and booleans.
- `implement-email/endpoints.md`: replace the `smtp-connections.php` route-file proposal with
  `routes/organization.php`; record the decided names (handlers, `SmtpConnectionRules`,
  `SmtpConnectionPayload`, `PathId`, `OperatorLog`, activation in the repositories, no
  `Pidgey\Email\ConnectionActivation`); mark implementation items 1 (CRUD, activate,
  deactivate — test email stays with #166), 2 (shared templates and redirection) and 3
  (`0010`) as landed for these endpoints, and item 6's operator log lines.

### Step 2 — Testing specs

- `testing/backend.md`: the lock-holder row names
  `source/tests/fixtures/scripts/organization-lock-holder.php` and its contract.
- `implement-email/testing.md`: tick "the lock-holder script".

## Files to Change
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`
- `docs/agents/specs/api/conventions/validation.md`
- `docs/agents/specs/implement-email/endpoints.md`
- `docs/agents/specs/testing/backend.md`
- `docs/agents/specs/implement-email/testing.md`

## Notes
- Do not tick the test-email items (#166) or anything owned by #165.
