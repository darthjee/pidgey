# Add the handlers, routes and operator log constants

Wire the HTTP layer, following `SmtpConnectionsHandler` / `SmtpConnectionHandler`.

- `OperatorLog::API_TOKEN_CREATE = 'api-token-create'` and `OperatorLog::API_TOKEN_REVOKE = 'api-token-revoke'`, next to the SMTP constants.
- `Pidgey\Http\Handler\ApiTokensHandler` (`GET`, `POST /api-tokens.json`): `build()` takes `$container->apiTokens()` and `$container->operatorLog()`; `process()` requires `AuthenticatedUser` and matches on method.
  - `GET` → `200 {"apiTokens": [ApiTokenPayload, ...]}`.
  - `POST` → `ApiTokenRules::forCreate($request->request()->json())`, `apiTokens->create($user->organizationId(), ...)`, log `API_TOKEN_CREATE` with `$user->id`, organization id and the new id, then `201 {"apiToken": new ApiTokenPayload($created->apiToken, $created->token)}`.
- `Pidgey\Http\Handler\ApiTokenHandler` (`DELETE /api-tokens/:id.json`): `const NOT_FOUND = 'API token not found'`; `PathId::parse($request, self::NOT_FOUND)` (gives the `400`/`404` behaviour); outcome `NotFound` → `NotFoundException(self::NOT_FOUND)`; `Revoked` → log `API_TOKEN_REVOKE`, `204`; `AlreadyRevoked` → `204`, no log.
- Routes in `source/config/routes/organization.php`, all `[Gate::class]`, no `AdminCheck`:
  `get /api-tokens.json`, `post /api-tokens.json` → `ApiTokensHandler`; `delete /api-tokens/:id.json` → `ApiTokenHandler`. Update the file docblock.

Tests (integration, `source/tests/integration/Http/Handler/`), modelled on `SmtpConnectionsHandlerTest`:

- `ApiTokensHandlerTest`: `GET` 200 in id order with revoked tokens, only own organization, no `tokenHash`/`organizationId`; `POST` with `{"name":" Moria ","scopes":["send"]}` → 201, name `Moria`, `token` matching `/^pdg_[0-9a-f]{64}$/`, stored digest = SHA-256 of it, `tokenPrefix` `pdg_`; duplicates deduplicated; validation failures → `400 VALIDATION_FAILED` with the expected `details`; anonymous → `401` on both methods; one `api-token-create` log line without the token or digest.
- `ApiTokenHandlerTest`: `204` with empty body and `revoked_at` set; second `DELETE` `204`, `revoked_at` unchanged, no second log line; foreign and unknown id → `404 API token not found`; `abc` / `0` → `400 BAD_REQUEST`; anonymous → `401`.

## Files to Change
- `source/lib/Http/OperatorLog.php` — two constants.
- `source/lib/Http/Handler/ApiTokensHandler.php` — new.
- `source/lib/Http/Handler/ApiTokenHandler.php` — new.
- `source/config/routes/organization.php` — three routes and docblock.
- `source/tests/integration/Http/Handler/ApiTokensHandlerTest.php` — new.
- `source/tests/integration/Http/Handler/ApiTokenHandlerTest.php` — new.
