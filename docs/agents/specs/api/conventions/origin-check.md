# Origin Check

Purpose: define the origin (CSRF) check applied to state-changing requests and its relation
with CORS.

## Rules

Every `POST`, `PUT`, `PATCH` and `DELETE` goes through the origin check, **including the
public routes**. It runs first, before authentication (see the guard order in
[format.md](format.md#guard-order)):

1. `GET` / `HEAD` / `OPTIONS` → always allowed.
2. If `Sec-Fetch-Site` is present: allowed when it is `same-origin` or `none`, or when the
   `Origin` header is in the trusted-origins list. Otherwise rejected.
3. If `Sec-Fetch-Site` is absent and `Origin` is absent → allowed (non-browser clients).
4. If `Sec-Fetch-Site` is absent and `Origin` is present → allowed when `Origin` is trusted or
   its host equals the `Host` header. Otherwise rejected.

Rejection: `403`, `{ "code": "FORBIDDEN", "message": "Cross-site request rejected" }`
(envelope in [errors.md](errors.md)).

Non-browser clients send neither `Origin` nor `Sec-Fetch-Site`, so server-to-server callers
of the public sending API (`POST /v1/messages` with a Bearer token) pass the check.

## Trusted origins and CORS

- The trusted-origins list is the same list that drives CORS: `ALLOWED_ORIGINS`
  (comma-separated bare origins, e.g. `https://admin.example.com`), falling back to the
  origin of `FRONTEND_BASE_URL`.
- When neither is set, CORS is off (same-origin only) and the trusted list is empty. This is
  the default: the admin UI is served from the same origin as the API.
- CORS, when on, is credentialed (`Access-Control-Allow-Credentials: true`).

## CSRF protection

This check is Pidgey's CSRF protection for cookie-authenticated requests, together with the
`SameSite` flag of the auth cookie (see [authentication.md](../authentication.md)). No CSRF
token header is used.

**Pending decision:** final names of the configuration variables (`ALLOWED_ORIGINS`,
`FRONTEND_BASE_URL` are the working names, without the Citadel prefix).
