# Issue: Origin check middleware

## Description

Part of epic #120 (auth API). Implement the origin-check (CSRF) middleware, `OriginCheck`, exactly as specified in `specs/api/conventions/origin-check.md`, and register it as the only global middleware: `new Router([OriginCheck::class])` in `source/config/routes.php`. It runs first, before the gate (guard order in `specs/request-handler/middlewares.md` and `specs/api/authentication/gate.md`).

## Problem

- The global middleware list in `source/config/routes.php` is empty "until OriginCheck lands". Nothing protects cookie-authenticated, state-changing requests from cross-site submission yet.
- Pieces the middleware needs are missing from the code:
  - `Request` has no `header()` accessor, although `specs/request-handler/request-response/request.md#headers` specifies one;
  - `ForbiddenException` is specified (`request-response/errors.md#exceptions`) but does not exist;
  - nothing reads the trusted-origins list (`ALLOWED_ORIGINS`, falling back to `FRONTEND_BASE_URL`).
- `ConfigValidator` does not check `ALLOWED_ORIGINS`, although the spec requires `https` entries in production. A malformed entry, such as one with a path, would never match, and nothing would say why.

## Expected Behavior

- **Checked methods:** every method except `GET`, `HEAD` and `OPTIONS` goes through the check: `POST`, `PUT`, `PATCH`, `DELETE`, and any other method, so the check fails closed. `GET`/`HEAD`/`OPTIONS`, and therefore the static frontend routes and the hash redirect, always pass.
- **Rules (from the spec):**
  - **`Sec-Fetch-Site` present:** the request is allowed when the value is exactly `same-origin` or `none`, or when `Origin` is trusted. Otherwise it is rejected.
  - **Neither `Sec-Fetch-Site` nor `Origin`:** allowed (non-browser clients).
  - **No `Sec-Fetch-Site`, `Origin` present:** allowed when `Origin` is trusted, or when the parsed `Origin` host **and** port equal the `Host` header's host and port. Otherwise it is rejected.
  - **`Origin: null`:** never trusted.
- **Parsing (rule 4):**
  - The host comparison ignores case.
  - A port the `Origin` leaves out is the default port of its scheme (`http` 80, `https` 443). A `Host` without a port takes the default port of the `Origin`'s scheme.
  - Bracketed IPv6 hosts (`[::1]:8080`) are supported.
  - An `Origin` with a path, query, user info, a scheme other than `http`/`https`, or no host cannot be parsed, so it is rejected.
- **Rejection:** `403`, `{ "code": "FORBIDDEN", "message": "Cross-site request rejected" }`. It is thrown as `ForbiddenException` and rendered by the shared error formatter. The middleware never calls `respond()`.
- **Trusted origins:**
  - `ALLOWED_ORIGINS` is split on `,`; entries are trimmed, empty entries dropped, and each is matched **exactly** against the request `Origin`.
  - When `ALLOWED_ORIGINS` is empty, the only trusted origin is the `scheme://host[:port]` part of `FRONTEND_BASE_URL`.
  - When both are empty, nothing is trusted (same-origin only).
- **Configuration validation:**
  - **Rules:** `ConfigValidator` adds two checks:
    - `ALLOWED_ORIGINS: invalid origin`: an entry is not a bare `scheme://host[:port]` origin;
    - `ALLOWED_ORIGINS: not https`: an `http` entry when `PHP_ENV` is not exactly `development` or `test`. This is the same environment rule as #125: unset counts as production.
  - **Effect:** `bin/check-config` exits `1`, and the web boot answers `500`, as for every invalid configuration. Failure lines never include the value.

## Solution

- **`Request::header(string $name, ?string $default = null): ?string`**, exactly as in `request-response/request.md#headers`:
  - lookup ignores case and reads only `$_SERVER`;
  - `Content-Type`/`Content-Length` map to keys without the prefix;
  - a missing header returns the default, and an empty one returns `""`;
  - the value is raw;
  - headers can be overridden through the constructor options for tests.

  #127 (JSON body parsing) reuses it.
- **`ForbiddenException`** (`403`, code `FORBIDDEN`), modeled on `NotFoundException`.
- **`TrustedOrigins`**, built from configuration. It exposes the list and an exact-match `contains(string $origin): bool`. The CORS step in #139 reuses it.
- **`OriginCheck`** (`BaseMiddleware`): `processRequest` applies the rules above and throws `ForbiddenException`. Origin and host parsing live in small private helpers that are unit-tested through the middleware.
- **`source/config/routes.php`:** `new Router([OriginCheck::class])`, and drop the "empty until OriginCheck lands" comment.
- **`ConfigValidator`:** add the two `ALLOWED_ORIGINS` checks.
- **Specs:**
  - `architecture/configuration.md` (product-owner): record the two `ALLOWED_ORIGINS` failure names and the bare-origin rule;
  - `api/conventions/origin-check.md`: write in the parsing details above (default ports, `Host` without a port, IPv6, unparseable forms).
- **Tests:**
  - **Unit:**
    - `Request::header()`;
    - `TrustedOrigins`, including the `FRONTEND_BASE_URL` fallback and exact matching;
    - `OriginCheck` for every rule: safe methods pass, `PUT` and unknown methods are checked, `same-origin`/`none`, cross-site with trusted and untrusted `Origin`, no headers, `Origin: null`, unparseable `Origin`, `Host` and port matching including default ports and IPv6;
    - the `ConfigValidator` cases.
  - **Integration:** through `FrontController`, the routes table has `OriginCheck` as its global middleware, and a cross-site `POST` gets the `403` envelope.
- **Review:** the `security` agent reviews before the PR is marked ready.
- **Out of scope:** the CORS front-controller step (`Pidgey\Http\Cors`), which is tracked in #139.

## Benefits

- Cookie-authenticated state changes are protected against CSRF before any auth endpoint lands (#130–#133).
- Server-to-server callers of the public API (no `Origin`, no `Sec-Fetch-Site`) are unaffected.
- A misconfigured `ALLOWED_ORIGINS` refuses boot instead of silently trusting nothing, or trusting plain-http origins in production.
- `Request::header()` and `TrustedOrigins` are shared building blocks for #127 and #139.
