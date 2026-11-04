# Issue: Auth endpoint: account edit and lockout

## Description
Part of epic #120 (auth API). Adds `PATCH /auth/account.json`: the logged-in user edits their own username, email and/or password, proving the current password each time, with a failed-attempt lockout. Spec: `docs/agents/specs/api/endpoints/auth/account.md` (lockout table: `organizations/data-model/account-edit-lockouts.md`; config: `ACCOUNT_EDIT_MAX_ATTEMPTS` default 5, `ACCOUNT_EDIT_LOCK_TTL` default 300 s).

Builds on the gate and shared `user` payload (#130), register/login (#131) and the existing `AccountEditLockoutRepository`, `UserRepository`, `PasswordResetTokenRepository`, `Passwords`, `Transaction` and `Router::patch()`.

## Problem
An approved user has no way to change their username, email or password. Without a lockout, the endpoint would also let a stolen session guess the current password without limit.

## Expected Behavior
- `PATCH /auth/account.json` is **gated**: anonymous, invalid sessions and non-`approved` users get the gate's uniform `401`.
- Body: `currentPassword` (required, never trimmed), optional `username` (trimmed, ≤255), `email` (trimmed, valid, ≤255), `newPassword` (min 8 chars, max 72 bytes, no NUL, never trimmed). Absent or `null` means "not present"; present-but-empty → `400 VALIDATION_FAILED`.
- Steps, in order:
  1. nothing to change → `400 BAD_REQUEST` `At least one of username, email, or newPassword is required` (not counted);
  2. in one transaction, upsert the caller's lockout row and lock it with `SELECT … FOR UPDATE`; clear an expired lock; if `locked_until > now` → commit and answer `423 LOCKED` `Account temporarily locked due to too many failed attempts`;
  3. caller row missing → `401 UNAUTHORIZED` `Invalid session`;
  4. checked in order, each failure counts as one attempt (the increment is committed before the error): wrong `currentPassword` → `400 BAD_REQUEST` `Invalid current password`; username taken by **another** user → `409 USERNAME_TAKEN`; email taken by another user → `409 EMAIL_TAKEN`;
  5. apply the changes (hash `newPassword`), mark every unused password-reset token as used when the email or password changes, reset the lockout row, commit → `200 {username, email}`. A unique-index violation on update applies nothing, answers the same `409` and counts as a failure.
- "Taken" checks only run when the new value differs byte-wise from the current one, exclude the caller's own row and use the column collation, so a case-only change (`Bob` → `bob`) is allowed.
- Lockout: the failure increment is an atomic upsert; locking uses `>=` against the incremented value (`locked_until = now + ACCOUNT_EDIT_LOCK_TTL`); a success resets the row; `now` comes from the `WallClock`.
- No digest rehash on edit; no session revocation on password change (documented limitation); the new email is not verified.

## Solution
- A `LockedException` (`423`, code `LOCKED`) next to the other HTTP exceptions.
- Repository additions: lockout upsert / lock-for-update / increment / reset / clear on `AccountEditLockoutRepository`; "taken by another user" lookups and an account update on `UserRepository`; "mark all unused tokens used" on `PasswordResetTokenRepository`.
- An `AccountEdit` service in `Pidgey\Auth` (built by the `Container`, reading both config keys and the `WallClock`) that runs steps 2–5 in one `Transaction`, so failure increments are committed rather than rolled back.
- A thin `AccountHandler` that validates the body, rejects the empty edit, calls the service and returns `200`; routed in `source/config/routes/auth.php` with `Router::patch()` and the `Gate` middleware.
- Tests (integration, against MySQL) cover:
  - each editable field;
  - a wrong current password;
  - lockout at exactly the limit, and a counter already above the limit still locking (`>=`);
  - `423` while locked, and an expired lock cleared before evaluation;
  - username and email uniqueness `409`s, including the unique-index race;
  - an allowed case-only change;
  - "nothing to change" not counted;
  - the 72-byte and NUL boundaries on `newPassword`;
  - reset tokens marked used on an email or password change, and untouched on a username-only change. The reset-password endpoint does not exist yet, so this is checked through `used_at`.
- Concurrency (the spec's "N parallel requests perform at most `ACCOUNT_EDIT_MAX_ATTEMPTS` password checks"):
  - a second database connection proves that the row lock serializes attempts;
  - a counting `Passwords` double proves the verify bound.
- A `security` agent review before the PR is marked ready.

## Benefits
Users can manage their own credentials, and a hijacked session cannot brute-force the current password, which is what protects the email and password changes from account takeover.
