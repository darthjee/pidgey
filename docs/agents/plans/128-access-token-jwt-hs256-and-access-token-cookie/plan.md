# Plan: Access token: JWT HS256 and `access_token` cookie

Issue: [128-access-token-jwt-hs256-and-access-token-cookie.md](../../issues/128-access-token-jwt-hs256-and-access-token-cookie.md)

## Overview
Implement the access-token building blocks already specified: the secret keys with the development-key boot refusal, a hand-rolled HS256 JWT signer and strict verifier, the `SetCookie` value object with the access-token set/clear lines, and `Request::cookie()`. The web app starts refusing to boot on an invalid configuration. Nothing consumes the token yet; the gate (#130) and the auth endpoints (#131, #132) will.

## Agents involved

- [product-owner](product-owner.md): records the decisions taken in the issue discussion in the specs.
- [infra](infra.md): the development `SECRET_KEY` in the docker compose `.env` and in the CircleCI test job.
- [backend](backend.md): the code and tests.

## Shared contracts

- **Development key:** exactly `pidgey-development-only-insecure-secret-key-0000` (48 bytes).
  - Code: `Pidgey\Infra\ConfigValidator::DEVELOPMENT_SECRET_KEY`.
  - Root `.env` (docker compose): `SECRET_KEY=pidgey-development-only-insecure-secret-key-0000`.
  - CircleCI `backend_tests` environment: `SECRET_KEY: pidgey-development-only-insecure-secret-key-0000` (`PHP_ENV: test` there, so it is allowed).
- **Boot refusal:** `FrontController::run()` validates the configuration before routing. Any failure throws a `RuntimeException` whose message lists the failure names (never values); the existing catch formats it as `500 INTERNAL_ERROR` and logs it. Every test that calls `FrontController::run()` therefore needs a valid environment: `DB_HOST`, `DB_NAME`, `DB_USER` and `SECRET_KEY`.
- **Claim formats** (decided in the issue): `sub` a JSON integer `>= 1`; `sid` a canonical lowercase UUID `^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$`, any version; `iat` and `exp` JSON integers. Floats (`1.0`), strings (`"1"`) and integers beyond `PHP_INT_MAX` are rejected.
- **`verify()`** returns `?AccessTokenClaims` (`null` on any failure, no reason returned or logged).
- **Clear cookie line:** `Set-Cookie: access_token=; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT`.
