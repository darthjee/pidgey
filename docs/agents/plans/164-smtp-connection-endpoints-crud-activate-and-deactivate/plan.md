# Plan: SMTP connection endpoints: CRUD, activate and deactivate

Issue: [164-smtp-connection-endpoints-crud-activate-and-deactivate.md](../../issues/164-smtp-connection-endpoints-crud-activate-and-deactivate.md)

## Overview

Add the seven SMTP connection admin endpoints (list, create, read, update, delete, activate,
deactivate) of `api/endpoints/pidgey-admin/smtp-connections.md`, in a new
`source/config/routes/organization.php`. The shared validator gains the integer, one-of,
hostname and character rules of `validation.md#message-templates`; migration `0010` adds the
database-level "at most one active connection" index; activate / deactivate run in one
transaction holding the organization row lock. The product owner records the decisions in the
permanent specs and ticks the checklist items.

## Agents involved

- [backend](backend.md)
- [product-owner](product-owner.md)

Security reviews the backend diff (read-only): canary password, credential redirection,
organization isolation, header-injection character rules.

## Shared contracts

- **Routes** (`source/config/routes/organization.php`, required from
  `source/config/routes.php` after `auth.php`, before the fallback); every route lists
  `[Gate::class]`; the global `OriginCheck` covers the writes:
  - `GET /smtp-connections.json`, `POST /smtp-connections.json` → `SmtpConnectionsHandler`;
  - `GET`, `PATCH`, `DELETE /smtp-connections/:id.json` → `SmtpConnectionHandler`;
  - `POST /smtp-connections/:id/activate.json` → `SmtpConnectionActivateHandler`;
  - `POST /smtp-connections/:id/deactivate.json` → `SmtpConnectionDeactivateHandler`.
- **`:id`:** `Pidgey\Http\PathId::parse(ProcessingRequest $request, string $notFound): int` —
  not `^[1-9][0-9]*$` → `BadRequestException('Validation failed (numeric string is
  expected)')`; above `PHP_INT_MAX` → `NotFoundException($notFound)`. Called first in each
  `:id` handler (after the gate, before body validation).
- **Validator additions** (`Pidgey\Http\Validator` / `FieldRules`, messages exactly as
  `validation.md#message-templates`):
  - `Validator::integer(string $field): IntegerRules` with `->optional()`,
    `->range(int $min, int $max)`; only a PHP `int` from the JSON is accepted (float, numeric
    string, bool → `<field> must be an integer`, no further rule);
  - `FieldRules::oneOf(string ...$values)` → `<field> must be one of the following values: a, b, c`;
  - `FieldRules::hostname()` → `<field> must be a hostname or an IP address`, through
    `Pidgey\Http\HostName::isValid(string): bool` (LDH, IPv4, IPv6 bare or bracketed; rejects
    Unicode, trailing dot, zone id, all-numeric non-IPv4 such as `999.1.1.1`);
  - `FieldRules::noControlCharacters()` → `<field> must not contain control characters`;
  - `FieldRules::noLineBreaks()` → `<field> must not contain line breaks`.
- **Fixed cross-field messages** (`Pidgey\Http\SmtpConnectionRules` constants):
  `username and password must be both set or both empty`;
  `username and password require encryption tls or ssl`;
  `password must be sent again when host, port or encryption changes`.
- **Operator log** — `Pidgey\Http\OperatorLog::record(string $action, int $userId, int
  $organizationId, int $connectionId, ?array $fields = null)`: Monolog `info`, message = action
  (`smtp-connection-create|update|delete|activate|deactivate`), context `userId`,
  `organizationId`, `connectionId`, `fields` (names only, update only).
- **Lock-holder script:** `source/tests/fixtures/scripts/organization-lock-holder.php` — args:
  organization id, hold milliseconds, optional connection id to activate before commit; prints
  `LOCKED` once `SELECT … FROM organizations WHERE id = ? FOR UPDATE` returns, holds the lock,
  then commits and exits `0`.
- **Migration:** `source/migrations/0010_add_smtp_connections_active_unique.sql` — stored
  generated `active_organization_id` (`IF(active, organization_id, NULL)`), unique index
  `uq_smtp_connections_active_organization_id`.
