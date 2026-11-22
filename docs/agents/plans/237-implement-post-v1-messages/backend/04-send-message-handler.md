# SendMessage handler, idempotency and routes/v1.php

**Route:** add `source/config/routes/v1.php` and require it from `config/routes.php` after `organization.php`. It registers:

```php
$router->post('/v1/messages', SendMessageHandler::class, [ApiTokenAuth::class, RequireSendScope::class]);
```

**The handler**, `Pidgey\Http\Handler\SendMessageHandler`:

1. Read the token `Caller`, then validate the payload (step 03).
2. When an `Idempotency-Key` header is present, run `IdempotencyKeys::validate()`; an invalid key answers `400`. Then run `begin($organizationId, $key, $message)`:
   - an `IdempotencyReplay` is returned verbatim (status and body) with `Idempotent-Replayed: true`;
   - an `IdempotencyConflictException` gives `422` or `409`;
   - a reservation continues the flow.
3. Resolve the organization's **active** connection with a new `SmtpConnectionRepository::findActiveInOrganization()` (`active = TRUE`). When there is none, answer `409 NO_SMTP_CONNECTION`.
4. Call `SendFlow::send($connection, $message, SendActor::token($tokenId))`.
   - When it is sent, answer `200 {"status":"sent","smtp":{"code","message"}}`.
   - Otherwise throw `SmtpFailureException::fromOutcome(...)`.
5. With a reservation in hand, every answer goes through `finish($reservation, $status, $body, $smtpStarted)`. `finish` decides between storing and releasing.
   - Error answers are rendered by `ErrorFormatter`, and that exact rendered response is the one returned, so the stored body and the sent body match.
   - `smtpStarted` is true once `SendFlow` was entered past the send-time checks. Use the outcome to tell a send-time `409` apart from a transaction failure.
   - An unexpected exception after the SMTP transaction started is stored as `500 INTERNAL_ERROR`.
6. Without the header, the `idempotency_keys` table is never touched.

Register the handler in the `Container` and `HandlerFactory`, following the pattern of `SmtpConnectionTestHandler::build()`.

**Tests (integration, with the fake SMTP server):** every "Testable rule" of `api/endpoints/sending.md`:
- `200` with the expected body;
- `401` cases with identical bodies;
- `403` without the `send` scope;
- `subject` of 255 and 256 characters;
- `413`;
- `409 NO_SMTP_CONNECTION` and the `SMTP_*` envelopes;
- the full idempotency list (no header means no row, store and replay with no SMTP call, `422`, `409` within the lease, an expired lease answered as a stored `504`, release on `503`, stored `SMTP_TIMEOUT` and `SMTP_PROTOCOL_ERROR`, invalid keys, the same key in two organizations, an expired key, cleanup bounded to 100 rows, `409 NO_SMTP_CONNECTION` storing nothing, a replay after the connection was deleted).

Also cover:
- the guard order: `401` before `403` before `400`;
- `GET /v1/messages` still answers a JSON `404`.

Update `RoutesSecurityTest`: `POST /v1/messages` lists `[ApiTokenAuth, RequireSendScope]` and never `Gate`.

## Files to Change
- `source/config/routes/v1.php`: new.
- `source/config/routes.php`: require `routes/v1.php`.
- `source/lib/Http/Handler/SendMessageHandler.php`: new.
- `source/lib/Repository/SmtpConnectionRepository.php`: `findActiveInOrganization()`.
- `source/lib/Container.php`, `source/lib/Http/HandlerFactory.php`: wiring, if needed.
- `source/tests/integration/Http/Handler/SendMessageHandlerTest.php` (several files if PHPMD complexity requires splitting): new.
- `source/tests/integration/Http/RoutesSecurityTest.php`: the `/v1` route assertion.
- `source/tests/integration/Http/HandlerFactoryRoutesTest.php`: include the new route, if it enumerates routes.
