# 04 — Handler, route and endpoint tests

**`Pidgey\Http\Handler\AccountHandler`** (`build()` from `$container->accountEdit()`):
- Validate: `currentPassword` required `notEmpty()` (never trimmed, no max); `username` `optional()->trim()->notEmpty()->maxLength(255)`; `email` `optional()->trim()->email()->maxLength(255)`; `newPassword` `optional()->minLength(8)->maxBytes(72)->noNulByte()` (reuse the `RegisterHandler` constants — move them to a shared place, e.g. `Passwords`, if cleaner).
- None of the three present → `BadRequestException('At least one of username, email, or newPassword is required')` (not counted; no DB access).
- Call `edit()` with `$request->require(AuthenticatedUser::class)->id`; answer `200` `JsonResponse` `{username, email}`. Never log the body.

**Route** in `source/config/routes/auth.php`: `$router->patch('/auth/account.json', AccountHandler::class, [Gate::class]);` — update the file docblock (no longer "every route is ungated"). It is **not** added to `RoutesSecurityTest::publicRoutes()`; the deny-by-default test must keep passing. Add it to `HandlerFactoryRoutesTest`.

**Endpoint tests** (`source/tests/integration/Http/Handler/AccountHandlerTest.php`, on `SessionEndpointTestCase`):
- gate: no cookie, invalid cookie, `pending` / `denied` user → uniform gate `401`; no lockout row created;
- validation: missing / empty `currentPassword`, empty `username` / `email`, bad email, 255/256 chars, `newPassword` 7 chars, 72 vs 73 bytes, NUL byte → `400 VALIDATION_FAILED` and not counted;
- `null` optional fields treated as absent; nothing to change → `400 BAD_REQUEST`, not counted;
- happy paths per field (and login with the new password works);
- `400 Invalid current password`, `409 USERNAME_TAKEN` / `EMAIL_TAKEN`, `423` after max failures — error envelope shape per `api/conventions/errors.md`;
- cross-site `Origin` → `403` from `OriginCheck` before the gate;
- `AuthLogTest`: no password, digest or token in the logged lines for failed and successful edits.

## Files to Change
- `source/lib/Http/Handler/AccountHandler.php` — new.
- `source/config/routes/auth.php` — route + docblock.
- `source/tests/integration/Http/Handler/AccountHandlerTest.php` — new.
- `source/tests/integration/Http/HandlerFactoryRoutesTest.php`, `AuthLogTest.php` — extend.
