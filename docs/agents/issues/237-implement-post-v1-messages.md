# Issue: Implement POST /v1/messages

## Description

`POST /v1/messages` is the public sending API, and it is fully specified:

- the endpoint: `docs/agents/api/endpoints/sending.md`;
- the token checks: `docs/agents/api/authentication/api-tokens.md`;
- the flow: `docs/agents/email/send-flow.md`;
- the payload: `docs/agents/email/message-scope.md`;
- the middleware names and order: `docs/agents/request-handler/middlewares.md`;
- the routing: `docs/agents/request-handler/routing.md`.

This issue was spawned while refining #221 (the `Idempotency-Key`). #221 and #233 (the size limits) are already merged.

It blocks #244 (`GET /v1/openapi.json`, whose public exception depends on the shape of the `/v1` gate) and the `clients_integration` job of epic #219.

## Problem

The backend already has most of the building blocks, but nothing serves the endpoint:

- **Already built:**
  - the shared `SendFlow`, used today by the SMTP connection test email;
  - `SendActor`, which already names the API-token actor;
  - the `Caller` interface;
  - the `api_tokens` table and repository, with lookup by hash;
  - the `IdempotencyKeys` service, with `begin` and `finish`, built by #221 but not wired to any route;
  - the router's reserved `/v1/` prefix.
- **Missing:**
  - Bearer authentication;
  - the scope check;
  - payload validation, including the 255-character `subject` cap;
  - the `/v1` route file;
  - the handler.

## Expected Behavior

`POST /v1/messages` behaves exactly as `sending.md` documents:

- **Authentication:**
  - every token failure answers the same `401 UNAUTHORIZED`: a missing, malformed, unknown or revoked token, or a token whose organization has no `approved` user;
  - in the no-approved-user case, `revoked_at` is not changed;
  - `last_used_at` is updated by `ApiTokenAuth` as soon as authentication succeeds, so a token later refused with a `403` (no `send` scope) still counts as used;
  - an `access_token` cookie is ignored.
- **Scope:** a valid token without scope `send` answers `403 FORBIDDEN`.
- **Payload:** validated per `message-scope.md`.
  - A `subject` of more than 255 characters answers `400 VALIDATION_FAILED`.
  - Malformed JSON, a `413` body and a `415` content type are answered as documented.
- **Send:** the synchronous `SendFlow` with the active connection.
  - Success answers `200 { "status": "sent", "smtp": { code, message } }`.
  - Every failure answers the error table of `sending.md`, using the error envelope.
- **Idempotency-Key:** in scope here. The handler connects the existing `IdempotencyKeys` service and honours the header exactly as documented: validation, `422`/`409`, store or release, and replay with `Idempotent-Replayed: true`. Without the header, no idempotency row is touched.
- **No change for existing routes:** the cookie surface does not change, and `Gate` keeps ignoring Bearer headers.

### Testing

PHPUnit (unit and integration, against the existing fake SMTP server) covers every item of the "Testable rules" list of `sending.md`, including the idempotency ones. It also covers:

- the guard order: `401` before `403` before payload validation;
- `last_used_at`: updated for a successful send and for a `403` scope refusal, and never for a `401`;
- `RoutesSecurityTest`: `/v1/messages` carries `ApiTokenAuth` and `RequireSendScope`, and never carries `Gate`;
- `GET /v1/messages` and other unmatched `/v1/*` requests still answer a JSON `404`.

## Solution

Follow the design already fixed in `request-handler/middlewares.md`:

1. **`ApiTokenAuth`**: a per-route middleware.
   - It reads `Authorization: Bearer <token>`, hashes it with SHA-256, looks it up, and checks that the token is not revoked and that its organization has at least one approved user.
   - It updates `last_used_at` and attaches the token as a `Caller`.
   - Every failure throws the same `UnauthorizedException`, and nothing about the reason is logged.
2. **`RequireScope` / `RequireSendScope`**: an abstract middleware with a `SCOPE` constant, and one subclass for `send`.
3. **The payload**: validation rules for the message payload (`to`, `subject` up to 255 UTF-8 characters, `text` and/or `html`), validated inside the handler.
4. **`routes/v1.php`**: registers `$router->post('/v1/messages', <handler>, [ApiTokenAuth::class, RequireSendScope::class])`.
5. **The handler**: in this order, it
   - validates the payload;
   - runs the `Idempotency-Key` step through `IdempotencyKeys` (only when the header is present);
   - resolves the active connection;
   - runs `SendFlow` with the token's `SendActor`;
   - maps the `SendOutcome` to `200` or the `SMTP_*` envelope;
   - stores or releases the key.
6. **As-built docs**:
   - remove the "Depends on #237" note in `sending.md`;
   - align `api-tokens.md` ("`last_used_at` is updated on successful use only") with `sending.md`: it is updated on a successful authentication, including when the scope check then answers `403`;
   - sync any class name that differs from `middlewares.md` and `routing.md`.

Out of scope: rate limits and CORS for browser clients (both pending in `api/pending.md`), and the OpenAPI document (#244).

## Benefits

- The public API exists, so the Node clients of #219 can run end to end against a real backend.
- The idempotency work of #221 becomes reachable.
- #244 can implement its public exception against a real, per-route gate.
