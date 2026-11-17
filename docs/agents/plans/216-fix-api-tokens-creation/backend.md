# Backend Plan: Implement the API tokens admin endpoints (list, create, revoke)

Main plan: [plan.md](plan.md)

## Overview

Add the missing HTTP and domain layers for API tokens, following the `Pidgey\Auth` domain-class pattern (`Registration`, `Login`, `AccountEdit`) and the `SmtpConnection*` handler/rules/payload pattern. No migration, no contract change, no frontend change, no `Pidgey\Service` namespace.

## Context

The admin UI (`ApiTokensClient`, `ApiTokensController`, `ApiTokenNewController`, `ApiTokenCreatedPanel`) calls endpoints documented in `docs/agents/api/endpoints/pidgey-admin/api-tokens.md` but never registered, so every call answers `404 NOT_FOUND`. Persistence already exists: migration `0007_create_api_tokens.sql`, `Pidgey\Model\ApiToken` (readonly, digest and prefix only), `ApiTokenRepository` (`create`, `findInOrganization`, `findByTokenHash`) and `Container::apiTokenRepository()`.

Pinned decisions (from the issue):

- Plain token: `ApiTokenValue::PREFIX` (`pdg_`) + `bin2hex(random_bytes(32))` (68 chars). `token_hash` = SHA-256 hex. `token_prefix` is always the literal `pdg_`.
- `name`: required string, trimmed, non-empty, ≤ 255 characters, not unique.
- `scopes`: required list (`is_array` **and** `array_is_list`), non-empty, each element exactly `send` (non-string elements count as unknown), deduplicated.
- All errors of a body are reported at once in one `400 VALIDATION_FAILED`.
- Revoke: atomic `UPDATE ... WHERE organization_id = ? AND id = ? AND revoked_at IS NULL`; `204` whether it changed or was already revoked; `404 API token not found` for unknown/foreign ids; log `api-token-revoke` only when a row changed.
- One operator log line on create (`api-token-create`) and on an effective revoke; never the plain token, digest or body.
- `#[SensitiveParameter]` on every parameter carrying the plain token.

## Steps

- [01 — Add ApiTokenValue and CreatedApiToken](backend/01-add-api-token-value.md)
- [02 — Extend ApiTokenRepository with list and revoke](backend/02-extend-api-token-repository.md)
- [03 — Add ApiTokenRules](backend/03-add-api-token-rules.md)
- [04 — Add the ApiTokens domain class and Container wiring](backend/04-add-api-tokens-domain-class.md)
- [05 — Add ApiTokenPayload](backend/05-add-api-token-payload.md)
- [06 — Add the handlers, routes and operator log constants](backend/06-add-handlers-and-routes.md)
- [07 — Route-level tests](backend/07-route-tests.md)

## CI Checks

- `source`: `make tests` (runs `composer lint && composer tests` in the `tests` container; CI jobs: `backend_checks`, `backend_tests`).

## Notes

- **Revoke outcome:** the issue sketches `ApiTokens::revoke(): bool`. To keep `404` out of the `Auth` layer while still distinguishing "not found" from "already revoked", step 04 uses an `ApiTokenRevokeOutcome` enum (`Revoked`, `AlreadyRevoked`, `NotFound`), matching the existing `AccountEditOutcome` style. Behaviour is identical to the issue.
- **Validator integration:** `Validator::validated()` throws as soon as its declared fields fail, so `ApiTokenRules` must collect `FieldRules('name')->errors($body)` and the `scopes` errors itself and throw a single `ValidationFailedException` with both.
- Do not touch `docs/agents/api/endpoints/pidgey-admin/api-tokens.md` (no `Cache-Control` change) nor the pagination Pending decision.
- Update the `source/config/routes/organization.php` docblock: API-token routes no longer "join later".
- The `ApiTokenRepository` docblocks still say "hashing happens in the service"; reword to "in `Pidgey\Auth\ApiTokens`".
