# Auth Endpoints

Purpose: index of the `/auth/*.json` endpoint specs and the rules they all share (guards,
trimming, identifiers, password hashing).

The auth endpoints follow the Citadel contract ([conventions.md](../conventions.md)) and the
authentication model ([authentication.md](../authentication.md)). Settings are defined in
[architecture/configuration.md](../../architecture/configuration.md).

| File | Content |
| --- | --- |
| [session.md](auth/session.md) | `POST /auth/register.json`, `POST /auth/login.json`, `POST /auth/refresh.json`, `DELETE /auth/logoff.json`, `POST /auth/status.json`. |
| [recovery.md](auth/recovery.md) | `POST /auth/recover.json`, `POST /auth/reset-password.json`, password-reset tokens, the recovery email. |
| [account.md](auth/account.md) | `PATCH /auth/account.json` and its failed-attempt lockout. |
| [device-authorization.md](auth/device-authorization.md) | `/auth/authorization-requests*.json`: device authorization (create, poll, mine, authorize, deny), its state machine and shared rules. |

Device authorization follows the guards and trimming rules below, with the additions
described in [device-authorization.md](auth/device-authorization.md).

## Guards

Every endpoint goes through the guard order of
[authentication/gate.md](../authentication/gate.md#guard-order): origin check (every
`/auth/*.json` endpoint is `POST`, `PATCH` or `DELETE`), gate, body validation, handler.

- **Public** (no gate): register, login, refresh, logoff, status, recover, reset-password,
  and the device-authorization create and poll.
- **Gated:** `PATCH /auth/account.json` and the device-authorization `mine`, `authorize` and
  `deny`. Only `approved` users pass the gate, so a `pending` caller gets the gate's uniform
  `401`.

## Trimming

- Surrounding whitespace is **trimmed** on `username`, `email` and `organizationName`
  **before validation**, on every endpoint that takes them (register, login, recover, account
  edit, device-authorization create). "Not empty" is checked after trimming, so a
  whitespace-only value is empty.
- Passwords (`password`, `currentPassword`, `newPassword`) are **never** trimmed.
- Tokens (`refreshToken`, `token`, `pollToken`) are taken as sent.

## Identifiers

- `username` and `email` are **stored as entered** (after trimming); their case is kept for
  display. PHP never lowercases them.
- Uniqueness and lookups rely on the `utf8mb4_0900_ai_ci` collation of the columns (case- and
  accent-insensitive, see
  [organizations/data-model/conventions.md](../../organizations/data-model/conventions.md#charset-and-collation)).
  Consequence: `bób` and `BOB` log in as `bob`, and register answers `USERNAME_TAKEN` for them.
- `username` and `email`: max **255 characters** (the `VARCHAR(255)` columns). `username` has
  no other character restriction.
- **Login is by `username` only.** Email is used only for password recovery; login by email
  is out of scope.

## Password hashing

- Every **new** password (register `password`, reset-password `password`, account
  `newPassword`) is hashed with `password_hash($password, PASSWORD_DEFAULT)` (bcrypt, cost 12
  on PHP 8.4) and checked with `password_verify()`, as in
  [organizations/data-model/organizations-and-users.md](../../organizations/data-model/organizations-and-users.md).
  There is no cost setting.
- Every new password has a **max length of 72 bytes** (UTF-8 byte length, message
  `<field> must be shorter than or equal to 72 bytes`), so bcrypt never silently truncates
  it, in addition to its min length of 8 characters.
- Every new password must **not contain a NUL byte** (`\0`): bcrypt rejects such a password
  (`password_hash()` throws), so it is a `400 VALIDATION_FAILED` with the message
  `<field> must not contain a NUL byte` instead of a server error.
- Passwords that are only **checked** (login `password`, account `currentPassword`,
  device-authorization authorize `password`) are only required to be non-empty strings: an
  over-long value simply fails verification.
- Passwords and tokens are never logged (see [authentication.md](../authentication.md)).

## Shared `user` payload

Register, login, refresh and the winning device-authorization poll return the
[shared `user` payload](../authentication/gate.md#shared-user-payload) and set the
`access_token` cookie ([access-token.md](../authentication/access-token.md#cookie)).

## Pending decisions

- **Pending decision:** a username character set (control characters, inner whitespace,
  confusable characters). Today any non-empty string up to 255 characters is accepted.
- **Pending decision:** an email-verification flow. It would allow register to become
  enumeration-safe (see the known limitation in [session.md](auth/session.md#known-limitation-enumeration))
  and would verify a new email in account edit.
- Each detail file lists its own.
