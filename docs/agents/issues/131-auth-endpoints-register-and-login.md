# Issue: Auth endpoints: register and login

## Description
Part of epic #120 (auth API). Implement `POST /auth/register.json` and `POST /auth/login.json` as specified in `docs/agents/specs/api/endpoints/auth/session.md`, following the shared rules in `docs/agents/specs/api/endpoints/auth.md` (guards, trimming, identifiers, password hashing). The frontend already calls both endpoints (`frontend/assets/js/client/AccountsClient.js`).

## Problem
The admin UI cannot create an account or log in: neither endpoint exists. The building blocks are on `main`: `OriginCheck`, `Validator`/`FieldRules`, `AccessToken` and `AccessTokenCookie`, `Gate`, `UserPayload`, `Transaction`, the user and organization repositories, and the sessions service from #129 (`Container::sessions()`, whose `start(User)` opens no transaction of its own).

## Expected Behavior
- **`POST /auth/register.json`** (public):
  - Validates `username`, `email`, `password` and `organizationName` per spec. Every field error is reported at once as `400 VALIDATION_FAILED`.
  - Pre-checks username and email with one query: `409 USERNAME_TAKEN` (checked first), otherwise `409 EMAIL_TAKEN`.
  - Hashes the password with `PASSWORD_DEFAULT`.
  - In one transaction, inserts the organization and the user, then starts a session. The user's status is `approved` only when `USER_APPROVAL_REQUIRED` is exactly `false`, otherwise `pending`.
  - A unique-index race rolls back the whole transaction, organization included, and answers the same `409`.
  - Answers `201` with the shared `user` payload plus `refreshToken`, and sets the `access_token` cookie.
- **`POST /auth/login.json`** (public):
  - Logs in by username only.
  - Always runs `password_verify()`. For an unknown username it verifies against a hard-coded `DUMMY_DIGEST`.
  - Unknown username or wrong password gives `401 UNAUTHORIZED` `Invalid username or password`.
  - Correct password with a `denied` user gives `403 USER_DENIED`, with no tokens and no cookie.
  - Correct password with a `pending` or `approved` user starts a new session and answers `201` with the shared payload and the cookie.
  - The password is rehashed when `password_needs_rehash()` is true.
- Both routes live in a new `source/config/routes/auth.php`. They are public: `OriginCheck` applies and there is no gate.
- Passwords, tokens and digests are never logged.

## Solution
- **Session start:** reuse `Sessions::start(User)` from #129 (`Container::sessions()`). It returns `SessionTokens` (plain refresh token, access-token JWT with `sid = session_uuid`, session id). It opens no transaction, so register calls it inside its own `Transaction::run()`. Login calls it directly.
- **Register handler:**
  - validation, with trimming of `username`, `email` and `organizationName`; the password is never trimmed;
  - the single-query pre-check;
  - `password_hash`;
  - a `Transaction::run()` that creates the organization, the user and the session;
  - on a unique violation (SQLSTATE `23000`), roll back, then re-run the pre-check to pick `USERNAME_TAKEN` or `EMAIL_TAKEN`.
- **Login handler:**
  - validation;
  - `findByUsername()`, relying on the collation;
  - `password_verify()` against the user's digest, or against the `DUMMY_DIGEST` constant, which is unit-tested with `password_needs_rehash()` returning false;
  - the status check after the password;
  - an optional rehash;
  - session start.
- **Response:** `201`, with the body `{ "user": UserPayload::fromUser($user), "refreshToken": "…" }` and a `Set-Cookie` from `AccessTokenCookie::set($jwt, ACCESS_TOKEN_TTL)`.
- **Config:** a `USER_APPROVAL_REQUIRED` reader (exact `false` means open registration; anything else, including unset, means approval is required). `REFRESH_TOKEN_TTL` is already handled by #129.
- **Tests:**
  - integration tests for success and for each error code in the spec, covering validation, both `409`s (the pre-check and a simulated unique-index race with rollback), `401`, `403 USER_DENIED`, a pending login, rehash, and `USER_APPROVAL_REQUIRED` on and off;
  - checks that the cookie and payload shape are right;
  - a check that no password or token appears in the logs.
- **Acceptance:** the `security` agent reviews the change before the PR is marked ready.

## Benefits
- Users can create an organization and log in from the admin UI.
- The first real gated flows (refresh, logoff, status, account edit) become testable end to end.
