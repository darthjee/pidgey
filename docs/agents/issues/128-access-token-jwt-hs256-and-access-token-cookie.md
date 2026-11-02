# Issue: Access token: JWT HS256 and `access_token` cookie

## Description
Part of epic #120 (auth API). The admin UI authenticates with a short-lived JWT access token carried only in the `access_token` cookie, signed with `SECRET_KEY`. This issue builds the token, its strict verification, the cookie helpers and the development-key boot refusal decided in #125. It depends on nothing open; the gate (#130) and the auth endpoints (#131, #132) build on it.

## Problem
Nothing in the backend reads `SECRET_KEY` yet: there is no JWT signing or verification, no way to write a `Set-Cookie` line through `Response`, and no `Request::cookie()`. The development-key rule from #125 is specified but not implemented, and the web app does not refuse to boot on an invalid configuration (only `bin/check-config` does).

## Expected Behavior
- A token signed by Pidgey verifies; every token that breaks a strict rule (tampered, expired, wrong algorithm or header, wrong key, malformed, bad claims) is rejected the same way.
- A token signed with a key in `PREVIOUS_SECRET_KEYS` still verifies; new tokens are always signed with `SECRET_KEY`.
- The access-token cookie is set and cleared with exactly the attributes in the spec.
- `bin/check-config` and the web boot refuse the development key outside `development`/`test`.
- Keys and tokens are never logged or returned.

## Solution

All rules are already specified; this issue implements them. Specs: `api/authentication/access-token.md`, `architecture/configuration.md#secret-keys`, `request-handler/request-response/response.md#setcookie`, `request-handler/request-response/request.md#cookies`, `request-handler/front-controller.md#failures-outside-the-wrapper`.

### Secret keys and the development key

- A `SecretKeys` value built from `SECRET_KEY` and `PREVIOUS_SECRET_KEYS` (split on `,`, entries trimmed): the current key signs; the current key, then each retired key in order, verify.
- A code constant holding the development key, `pidgey-development-only-insecure-secret-key-0000`, and the line `SECRET_KEY=pidgey-development-only-insecure-secret-key-0000` in the committed root `.env` used by docker compose.
- `ConfigValidator` gains the development-key check: an exact byte comparison against `SECRET_KEY` and each trimmed `PREVIOUS_SECRET_KEYS` entry, refusing unless `PHP_ENV` is exactly `development` or `test`. It runs after the missing / empty-entry / length checks, and reports `SECRET_KEY: development key` and `PREVIOUS_SECRET_KEYS: development key`, each at most once, never with a value.
- **Web boot refusal:** `public/index.php` runs the same validation at boot. On any failure it logs the failure names (never values) and answers the `500 INTERNAL_ERROR` envelope. This replaces the "web boot refusal not wired yet" note in `configuration.md`. Every request, static files included, then needs a valid configuration; any test or CI job that boots `index.php` gets a valid `SECRET_KEY` (the CircleCI `backend_tests` environment included, if needed).

### JWT HS256

- Hand-rolled (base64url, `hash_hmac('sha256')`, `hash_equals()`), no JWT library.
- **Sign:** the fixed header `{"alg":"HS256","typ":"JWT"}`; claims `sub`, `sid`, `username`, `isAdmin`, `iat`, `exp = iat + ACCESS_TOKEN_TTL`; always the current `SECRET_KEY`; base64url without padding.
- **Verify:** every strict rule of `access-token.md#verification-strict-rules`, in order: exactly three segments; strict canonical base64url (re-encoding gives back the same segment); header byte for byte equal to the fixed header; signature compared in constant time against the current key, then each retired key; payload decoded only after the signature matches, and it must be a JSON object with `sub` a JSON integer `>= 1`, `sid` a canonical lowercase UUID, `iat`/`exp` JSON integers, `iat <= now`, `exp > now` (no leeway), and `exp - iat <= ACCESS_TOKEN_TTL`. Floats (`1.0`) and integers beyond `PHP_INT_MAX` are not integers.
- `verify()` returns the trusted claims (`sub`, `sid`, `iat`, `exp`) or `null`. Every failure looks the same; no reason is returned or logged, and the token is never logged.
- `now` comes from the injected `WallClock` (UTC), so tests control time.

### Cookies

- `SetCookie` value object (per `response.md#setcookie`): builds one `Set-Cookie:` line from name, value, `Path`, `Max-Age`, `Expires`, `HttpOnly`, `Secure`, `SameSite`; rejects CR, LF, `;` and `,` in names and values; never uses `setcookie()`.
- Access-token helpers:
  - set: `access_token=<jwt>; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=<ACCESS_TOKEN_TTL>`;
  - clear: `access_token=; Path=/; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT` with the same `HttpOnly; Secure; SameSite=Strict`.
- `Request::cookie(string $name, ?string $default = null)` per `request.md#cookies` (array values return the default; `headers()` still leaves out `cookie`).
- No handler or middleware uses any of this yet: the gate (#130) and the auth endpoints (#131, #132) do.

## Benefits
- Unblocks the gate (#130) and the session endpoints (#131, #132).
- A small, dependency-free JWT with the usual pitfalls (`alg: none`, algorithm confusion, key selection by header, non-constant-time compare) ruled out by construction.
- A production host can no longer run with the shipped development key.

## Acceptance
- Unit tests for valid tokens, key rotation, and every rejection rule.
- Tests for `SetCookie` (attributes, rejected characters), the set/clear lines, `Request::cookie()`, the development-key check and the web boot refusal.
- `security` agent review before the PR is marked ready.
