# Backend Plan: Implement POST /v1/messages

Main plan: [plan.md](plan.md)

## Overview

Build `POST /v1/messages` from the pieces that already exist:

| Piece | Location |
| --- | --- |
| `SendFlow` | `lib/Email/SendFlow.php` |
| `SendActor::token()` | `lib/Email/SendActor.php` |
| `Caller` | `lib/Auth/Caller.php` |
| `ApiTokenRepository::findByTokenHash()` | `lib/Repository/ApiTokenRepository.php` |
| `IdempotencyKeys` (`validate`, `begin`, `finish`) | `lib/Email/IdempotencyKeys.php` |
| `SmtpFailureException::fromOutcome()` | `lib/Http/SmtpFailureException.php` |
| `ErrorFormatter` | `lib/Http/ErrorFormatter.php` |
| `RouteGuards` (it already knows `Pidgey\Auth\ApiTokenAuth`) | `tests/support/RouteGuards.php` |

## Context

The design is fixed:

| Concern | Doc |
| --- | --- |
| Middleware names and order | `docs/agents/request-handler/middlewares.md` |
| Routing | `docs/agents/request-handler/routing.md` |
| Endpoint contract | `docs/agents/api/endpoints/sending.md` |
| Token checks | `docs/agents/api/authentication/api-tokens.md` |
| Payload | `docs/agents/email/message-scope.md` |
| Idempotency flow | `docs/agents/email/send-flow.md#idempotency-key-step` |

Decisions taken while refining the issue:
- The `Idempotency-Key` is wired here, not in a follow-up.
- `last_used_at` is updated by `ApiTokenAuth` as soon as authentication succeeds. A token later refused with a `403` therefore still counts as used.

## Steps

- [01 — ApiTokenAuth and the token Caller](backend/01-api-token-auth.md)
- [02 — RequireScope and RequireSendScope](backend/02-require-send-scope.md)
- [03 — Message payload validation](backend/03-message-payload.md)
- [04 — SendMessage handler, idempotency and routes/v1.php](backend/04-send-message-handler.md)
- [05 — As-built docs](backend/05-as-built-docs.md)

## CI Checks

- `source/`: `docker compose run --rm tests sh -c "composer lint && composer tests"`
  - It is also the first half of `make tests`.
  - CI job: `backend_checks`.

## Notes

- **Approved-user check:** "the token's organization has at least one `approved` user" needs a new query. Prefer joining it into the token lookup (one query, as the gate does) over a second round trip. Either way, every failure throws the same `UnauthorizedException`.
- **`last_used_at`:** the update is a plain `UPDATE ... SET last_used_at = UTC now` on every successful authentication. A failed update must not be swallowed.
- **Rendering stored answers:** errors normally reach the envelope through the front controller. To store an error answer, the handler must render the exception with the same `ErrorFormatter` (same `timestamp`), call `finish()`, and then return that same rendered response. Otherwise the stored body and the body sent to the client would differ.
- **Out of scope:**
  - rate limits and CORS for browser clients (pending in `api/pending.md`);
  - the OpenAPI document (#244).
- **#244:** it relies on the gate being per-route middleware. Keep it that way: no prefix-wide `/v1` guard.
