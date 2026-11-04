# Backend Plan: Auth endpoint: account edit and lockout

Main plan: [plan.md](plan.md)

## Overview
Implement `PATCH /auth/account.json` exactly as specified in `docs/agents/specs/api/endpoints/auth/account.md` (lockout table: `organizations/data-model/account-edit-lockouts.md`; config: `architecture/configuration.md`). A thin `AccountHandler` validates the body; an `AccountEdit` service in `Pidgey\Auth` runs the lock / verify / taken / apply sequence in one transaction holding the caller's lockout row `FOR UPDATE`.

## Context
- The gate (`Pidgey\Auth\Gate`) already attaches an `AuthenticatedUser` (`id`, `sessionId`, `username`, `isAdmin`, `organizationId`) to the `ProcessingRequest`; the handler reads it with `$request->require(AuthenticatedUser::class)`.
- `Router::patch()` exists; auth routes live in `source/config/routes/auth.php`.
- `ErrorFormatter` already maps `423` → `LOCKED` / `Locked`; there is no `LockedException` class yet.
- `Validator` / `FieldRules` only know **required** string fields; the account body has three optional ones.
- `AccountEditLockoutRepository` only has `create`, `findById`, `findByUserId`. `UserRepository` has `findTaken`, `findByUsername`, `findByEmail`, `updatePasswordDigest`. `PasswordResetTokenRepository` has no bulk "mark used".
- `Passwords` is `final` (`hash`, `canHash`, `verify`, `needsRehash`); password rules (min 8 chars, max 72 bytes, no NUL) are already declared in `RegisterHandler` via `PASSWORD_MIN_CHARACTERS` / `PASSWORD_MAX_BYTES`.
- `Registration::create()` shows the integrity-violation (`SQLSTATE 23000`) handling to mirror for the update race, never chaining the driver message (it quotes user data).
- `ConfigValidator` already lists `ACCOUNT_EDIT_MAX_ATTEMPTS` and `ACCOUNT_EDIT_LOCK_TTL`; there are no default constants for them yet.
- `SessionEndpointTestCase::dispatch()` always sends `'cookies' => []`, so gated requests cannot be exercised yet.

## Steps

- [01 — Building blocks: optional fields, LockedException, test support](backend/01-building-blocks.md)
- [02 — Repository additions](backend/02-repositories.md)
- [03 — AccountEdit service and container wiring](backend/03-account-edit-service.md)
- [04 — Handler, route and endpoint tests](backend/04-handler-and-route.md)

## CI Checks
- `source`: `composer lint` (CI job: `backend_checks`)
- `source`: `composer coverage` (CI job: `backend_tests`; needs MySQL — locally `make tests`)

## Notes
- Follow the spec's step order literally: empty edit (not counted) → lock row + expired-lock clear + `423` → caller row missing `401 Invalid session` → wrong password `400` → `USERNAME_TAKEN` → `EMAIL_TAKEN` → apply.
- Every failure **after** the lock check must commit its increment before the error is answered: the failure is returned out of the transaction callback (or the transaction is committed, then the exception thrown) — never thrown inside `Transaction::run()`, which would roll the increment back. Same for the `423` path (commit after clearing nothing / reading the lock).
- Threshold uses `>=` on the incremented value; `locked_until = now + ACCOUNT_EDIT_LOCK_TTL`; `now` always comes from `WallClock` and is bound from PHP (no SQL `NOW()`).
- "Taken" checks only when the value differs byte-wise (`!==`) from the current one, exclude `id <> caller`, and use the column collation (do not compare in PHP).
- No rehash on edit; no session revocation on password change; email is not verified. Do not log the body; `#[SensitiveParameter]` on every password / digest parameter.
- Reset-password (`recovery.md`) is not implemented yet: tests assert `used_at` on `password_reset_tokens` rows instead of calling the endpoint.
- The spec's "N parallel requests perform at most `ACCOUNT_EDIT_MAX_ATTEMPTS` password checks" is covered by (a) a second PDO connection holding the lockout row `FOR UPDATE` to prove an attempt blocks on it before any password check, and (b) a counting password verifier over N > max sequential failures. No forked processes.
- A `security` agent review is required before the PR is marked ready.
