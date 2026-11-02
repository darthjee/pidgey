# Issue: Sessions service: refresh tokens, rotation, replay detection

## Description
Part of epic #120 (auth API). Build the sessions service that `docs/agents/specs/api/authentication/sessions.md` defines:
- starting a session;
- atomic rotation;
- replay detection;
- revocation with reasons.

The `refresh_tokens` table, the `RefreshToken` entity, the `RevokedReason` enum and `RefreshTokenRepository` (create and lookups only) already exist (#87). The access token (#128) and the gate (#130) are merged.

## Problem
The session endpoints need a single place that owns the refresh-token rules: register and login (#131), refresh, logoff and status (#132), and later password reset and admin deny. Without it, every handler would re-implement the hashing, the clock rules, the conditional revoke and the replay logic, with a risk of small, security-relevant differences.

## Expected Behavior
- **Start** (login, register, winning device poll):
  - the refresh token is 48 bytes from `random_bytes`, hex-encoded (96 characters);
  - only its SHA-256 hex hash is stored, under a new random UUID v4 `session_uuid`;
  - `issued_at = started_at = now` and `expires_at = now + REFRESH_TOKEN_TTL` (default `604800`);
  - an access token is issued with `sid = session_uuid`;
  - the plain refresh token and the JWT are returned.
- **Refresh**, following the order of the spec's table:
  - the presented token is unknown or expired: plain failure;
  - it was revoked as `rotated`: every active token of the user is revoked with `replay_detected` (committed), then failure;
  - it was revoked for any other reason: failure;
  - the user row is missing: failure;
  - the user is `denied`: the presented token is revoked with `admin_denied`, then failure;
  - otherwise the token is rotated in one transaction:
    1. insert a new row that copies `session_uuid` and `started_at`;
    2. revoke the presented row with `rotated`, but only `WHERE id = ? AND revoked_at IS NULL AND expires_at > ?`;
    3. if that affects 0 rows, roll back and fail with no side effect;
    4. return the user, the new plain refresh token and a new JWT with the same `sid`.

  Every failure is the same outcome, so callers cannot tell the cases apart.
- **Logout:** look up the token whatever its state, then revoke every active row with the same `user_id` and `session_uuid` with `logout`. An unknown token is a silent no-op.
- **Revoke all for a user** with a given reason (`password_reset`, `admin_denied`, `admin_edit`). It only touches active rows, so already-revoked rows keep their reason.
- **Status support:** a passive lookup of an *active* token (found, `revoked_at IS NULL`, `expires_at > now`) with its user, plus revoking one presented token with `admin_denied`. It never rotates and never runs replay detection.
- **Clock and storage:**
  - every `now` comes from `WallClock` (UTC) and is bound as a parameter; SQL never calls `NOW()`;
  - `revoked_at` and `revoked_reason` are always written together;
  - plain tokens are never stored or logged.

## Solution
- **`Pidgey\Auth\Sessions`**, a service built through `Container::sessions()` from the refresh-token and user repositories, `Transaction`, `AccessToken`, `WallClock` and the `REFRESH_TOKEN_TTL` setting. It has these methods:
  - `start(User): SessionTokens`. It does not open a transaction of its own, so register can call it inside its own transaction;
  - `refresh(string $plain): ?RefreshedSession`, where null is the uniform failure;
  - `logout(string $plain): void`;
  - `revokeAllForUser(int $userId, RevokedReason $reason): void`;
  - `findActive(string $plain): ?ActiveSession`, holding the token and its user, for status;
  - `revoke(RefreshToken, RevokedReason): void`.
- **`SessionTokens`** (`refreshToken`, `accessToken`, `sessionUuid`): a small readonly value. Its plain token values are marked `#[SensitiveParameter]` and kept out of `__toString` and debug output.
- **`RefreshTokenRepository`** additions, each taking `now` as a bound parameter and returning the number of rows affected:
  - `revokeIfActive(int $id, RevokedReason, DateTimeImmutable $now): int` (the conditional revoke);
  - `revokeActiveForUser(int $userId, RevokedReason, DateTimeImmutable $now): int`;
  - `revokeActiveForSession(int $userId, string $sessionUuid, RevokedReason, DateTimeImmutable $now): int`.
- **Config:** a public `DEFAULT_REFRESH_TOKEN_TTL` constant shared by `ConfigValidator` and the container, like `DEFAULT_ACCESS_TOKEN_TTL`.
- **No endpoints** here. Handlers come in #131 (register and login) and #132 (refresh, logoff and status).

## Benefits
- One audited implementation of the session rules, which keeps the endpoint issues small.
- Logout, password reset, replay detection and admin deny take effect on the next request, through the gate's per-request session check.

## Acceptance
- **Unit and integration tests:**
  - start: token format and length, the hash stored instead of the plain value, the UUID, the TTL, and the JWT `sid`;
  - refresh, each branch of the table:
    - success copies `session_uuid` and `started_at` and revokes the old row with `rotated`;
    - unknown, expired, otherwise-revoked token and missing user all fail;
    - replay revokes every active token of the user with `replay_detected`, and other users are untouched;
    - a denied user's token is revoked with `admin_denied`;
  - lost race: the presented row is revoked between the lookup and the conditional revoke (simulated in the test), so rotation affects 0 rows. The transaction rolls back, no new row remains, and no replay revocation happens;
  - logout revokes only that session, and an unknown token is a no-op;
  - the bulk revoke leaves already-revoked rows' reasons unchanged;
  - `findActive` ignores revoked and expired tokens.
- No plain token appears in logs or exception messages.
- The `security` agent reviews the change before the PR is marked ready.
