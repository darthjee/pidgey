# ApiTokenAuth and the token Caller

Add `Pidgey\Auth\ApiTokenAuth`. It is a `BaseMiddleware` with a `build(Container)` method, mirroring `Gate`. Its job is to authenticate the Bearer token of a `/v1` request.

1. Read `Authorization`. Anything other than a well-formed `Bearer <token>` is a `401`.
2. Hash the token with SHA-256, using the same helper `ApiTokens`/`ApiTokenValue` use at creation, and find the row.
3. Refuse the token (`401`) when the row is unknown or `revoked_at` is set.
4. Refuse the token (`401`) when its organization has no `approved` user. Change no data in this case.
5. On success:
   - update `last_used_at` to the wall-clock now (UTC);
   - attach the token as a `Caller`, with `organizationId()` and its id and scopes, for the scope check and `SendActor::token()`.

Rules that apply to every failure:
- It throws the **same** `UnauthorizedException` (same message for every reason).
- Nothing about the token or the reason is logged.
- The `access_token` cookie is never read.
- Database errors propagate as a `500`.

Add the middleware to the `Container` wiring the same way `Gate` is built.

**Tests:**
- Unit: header parsing (missing, `Basic`, empty, extra spaces).
- Integration, against the database:
  - a valid token attaches the `Caller` and sets `last_used_at`;
  - unknown, revoked and no-approved-user tokens all produce an identical `401` body;
  - `revoked_at` is unchanged in the no-approved-user case;
  - `last_used_at` is untouched on any `401`;
  - a cookie alone is a `401`.

## Files to Change
- `source/lib/Auth/ApiTokenAuth.php`: new middleware.
- `source/lib/Auth/AuthenticatedApiToken.php` (or a similar name): new, the attached `Caller` (id, organizationId, scopes).
- `source/lib/Repository/ApiTokenRepository.php`:
  - a lookup by hash that also requires an approved user in the organization;
  - `touchLastUsedAt(int $id, DateTimeImmutable $now)`.
- `source/lib/Container.php`: wiring, if the middleware needs any.
- `source/tests/unit/Auth/ApiTokenAuthTest.php`, `source/tests/integration/Auth/ApiTokenAuthTest.php`: new.
- `source/tests/integration/Repository/ApiTokenRepositoryTest.php`: the new queries.
