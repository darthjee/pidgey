# Plan: Origin check middleware

Issue: [126-origin-check-middleware.md](../../issues/126-origin-check-middleware.md)

## Overview

Add the `OriginCheck` global middleware, Pidgey's CSRF protection, as specified in `specs/api/conventions/origin-check.md`. Register it with `new Router([OriginCheck::class])`.

The building blocks it needs are added in the same issue:

- `Request::header()`;
- `ForbiddenException`;
- a `TrustedOrigins` helper, which #139's CORS step will reuse;
- two `ALLOWED_ORIGINS` checks in `ConfigValidator`.

The product owner first writes the decided parsing and validation details into the specs; the backend then implements them.

## Agents involved

- [product-owner](product-owner.md)
- [backend](backend.md)

## Shared contracts

- **Checked methods:** every method except `GET`, `HEAD` and `OPTIONS`.
- **Rejection:** `403`, code `FORBIDDEN`, message `Cross-site request rejected`. It is thrown as `Pidgey\Http\ForbiddenException`, never returned through `respond()`.
- **`Sec-Fetch-Site`:** exact, case-sensitive match against `same-origin` and `none`.
- **Origin parsing:**
  - The scheme must be `http` or `https`.
  - A host is required. Bracketed IPv6 hosts (`[::1]`) are allowed.
  - The port is optional and defaults to 80 for `http` and 443 for `https`.
  - Any path (except none at all), query, fragment or user info makes the `Origin` unparseable.
  - `null`, or anything unparseable, is rejected under rule 4. It is never trusted under rule 2.
- **Host comparison (rule 4):**
  - The host comparison ignores case.
  - A `Host` without a port takes the default port of the `Origin`'s scheme.
  - A missing or unparseable `Host` rejects the request.
- **Trusted origins:**
  - `ALLOWED_ORIGINS` is split on `,`; entries are trimmed, and empty entries dropped.
  - When `ALLOWED_ORIGINS` has no entries, the list is `[scheme://host[:port]]` of `FRONTEND_BASE_URL`, if set and parseable.
  - Otherwise the list is empty.
  - Matching is an exact string comparison against the request `Origin`.
- **Config failure lines** (they never include the value):
  - `ALLOWED_ORIGINS: invalid origin`: an entry is not a bare `scheme://host[:port]` origin with an `http`/`https` scheme. A single trailing `/` also counts as invalid.
  - `ALLOWED_ORIGINS: not https`: an `http` entry while `PHP_ENV` is not exactly `development` or `test`. Unset counts as production.
  - Each failure kind is reported once, like `PREVIOUS_SECRET_KEYS`.
