# Architect-owned files
The architect applies this step itself, because these files are outside product-owner's scope.

- `docs/agents/architecture.md`: change "the admin session endpoints" to the auth and admin
  endpoints (JWT access cookie).
- `.claude/agents/security.md`: change the "Authentication & sessions" bullet to: password
  hashing, the JWT access cookie (strict HS256 verification, cookie flags), refresh-token
  rotation and replay detection, the per-request gate, and the origin check as CSRF protection.
  Change the "API tokens" bullet to say Bearer tokens are only accepted on `/v1`.

## Files to Change
- `docs/agents/architecture.md`
- `.claude/agents/security.md`
