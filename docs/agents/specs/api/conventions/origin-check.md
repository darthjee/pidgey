# Origin Check

Purpose: define the origin (CSRF) check applied to state-changing requests and its relation
with CORS.

## Rules

Every `POST`, `PUT`, `PATCH` and `DELETE` goes through the origin check, **including the
public routes**. It runs first, before authentication (see the guard order in
[format.md](format.md#guard-order)):

1. `GET` / `HEAD` / `OPTIONS` → always allowed.
2. If `Sec-Fetch-Site` is present: allowed when it is `same-origin` or `none`, or when the
   `Origin` header is in the trusted-origins list. Otherwise rejected. `Origin: null` is never
   trusted.
3. If `Sec-Fetch-Site` is absent and `Origin` is absent → allowed (non-browser clients).
4. If `Sec-Fetch-Site` is absent and `Origin` is present → allowed when `Origin` is trusted,
   or when the `Origin` is parsed and both its host **and** port (the scheme's default port
   when omitted) are equal to the host and port of the `Host` header. Otherwise rejected.
   `Origin: null`, or an `Origin` that cannot be parsed, is rejected.

Rejection: `403`, `{ "code": "FORBIDDEN", "message": "Cross-site request rejected" }`
(envelope in [errors.md](errors.md)).

Because rule 1 lets them through unconditionally, `GET` and `HEAD` requests must **never
change state** on any endpoint; every state change uses `POST`, `PUT`, `PATCH` or `DELETE`.

Non-browser clients send neither `Origin` nor `Sec-Fetch-Site`, so server-to-server callers
of the public sending API (`POST /v1/messages` with a Bearer token) pass the check.

Browser callers of `POST /v1/messages` from another origin are **rejected** unless that
origin is trusted. This is intended: API tokens are meant for server-side callers and must
not live in browsers. The trusted-origins list must not be widened to allow such callers.

## Trusted origins and CORS

- The trusted-origins list is the same list that drives CORS: `ALLOWED_ORIGINS`
  (comma-separated bare origins, e.g. `https://admin.example.com`), falling back to the
  origin of `FRONTEND_BASE_URL`.
- When neither is set, CORS is off (same-origin only) and the trusted list is empty. This is
  the default: the admin UI is served from the same origin as the API.
- An `ALLOWED_ORIGINS` entry matches the request `Origin` **exactly** (scheme, host and
  port). `*`, wildcards and suffix or pattern matching are not supported.
- In production, every `ALLOWED_ORIGINS` entry must use the `https` scheme.
- `Origin: null` is never trusted, whatever the configuration.
- The API never reflects an arbitrary `Origin`: `Access-Control-Allow-Origin` is sent only
  when the request `Origin` matches a trusted origin, and it echoes that matched origin.
- Responses that depend on the `Origin` header send `Vary: Origin`.
- CORS, when on, is credentialed: `Access-Control-Allow-Credentials: true` is sent only
  together with an `Access-Control-Allow-Origin` echoing the matched trusted origin.

## CSRF protection

This check is Pidgey's CSRF protection for cookie-authenticated requests, together with the
`SameSite` flag of the auth cookie (see [authentication/access-token.md](../authentication/access-token.md#cookie)). No CSRF
token header is used.

The variables `ALLOWED_ORIGINS` and `FRONTEND_BASE_URL` are defined in
[architecture/configuration.md](../../architecture/configuration.md#origins).

## Pending decisions

None.
