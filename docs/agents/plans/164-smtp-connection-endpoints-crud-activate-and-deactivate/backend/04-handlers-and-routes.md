# Handlers and routes

- `SmtpConnectionsHandler`: `GET` → list ordered by id; `POST` → `forCreate`, then in one
  `Transaction::run`: `create(..., encryptedPassword: null)`, and when a password is given
  `PasswordCipher::encrypt($password, $orgId, $newId)` + `setPassword`; reload; `201`; log
  `smtp-connection-create`. An encryption failure rolls back and surfaces as `500` (the
  existing error formatter; no password data in the logged error).
- `SmtpConnectionHandler`: `PathId` first; `GET` → `200` or `404`; `PATCH` → one transaction:
  `lockInOrganization` (`404` when null), `forUpdate`, encrypt a new password with a fresh
  nonce, `update` (password bytes kept when not changed), reload, `200`; log
  `smtp-connection-update` with `fields` only when something changed (empty body → `200`, no
  log); `DELETE` → `204`, log `smtp-connection-delete`.
- `SmtpConnectionActivateHandler` / `SmtpConnectionDeactivateHandler`: `PathId`; one
  transaction: `OrganizationRepository::lockForUpdate`, load the target (`404`), `activate` /
  `deactivate`, reload; `200`; log. Body ignored. A unique-index violation is a bug → `500`.
- Organization id always from `AuthenticatedUser::organizationId()` (gate), never the body.
- `NotFoundException('SMTP connection not found')` for unknown, out-of-range or foreign ids.
- `source/config/routes/organization.php` with the routes of the shared contract, required
  from `routes.php` after `auth.php`; `HandlerFactory` / `RouteGuards` pick them up (gated).

## Files to Change
- `source/lib/Http/Handler/SmtpConnectionsHandler.php`, `SmtpConnectionHandler.php`,
  `SmtpConnectionActivateHandler.php`, `SmtpConnectionDeactivateHandler.php` — new.
- `source/config/routes/organization.php` — new; `source/config/routes.php` — require it.
- `source/lib/Container.php` — any new wiring.
- `source/tests/integration/Http/HandlerFactoryRoutesTest.php`, `RoutesSecurityTest.php` —
  new routes covered.
