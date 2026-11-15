# Origin Check

Purpose: define the origin (CSRF) check applied to state-changing requests and its relation
with CORS.

## Rules

Every method except `GET`, `HEAD` and `OPTIONS` goes through the origin check, **including
the public routes**: `POST`, `PUT`, `PATCH`, `DELETE`, and any other or unknown method, so the
check fails closed. It runs first, before authentication (see the guard order in
[format.md](format.md#guard-order)):

1. `GET` / `HEAD` / `OPTIONS` → always allowed. The static frontend routes and the hash
   redirect are therefore never affected.
2. If `Sec-Fetch-Site` is present: allowed when it is exactly `same-origin` or `none`, or
   when the `Origin` header is in the trusted-origins list. Otherwise rejected.
   `Origin: null` is never trusted.
3. If `Sec-Fetch-Site` is absent and `Origin` is absent → allowed (non-browser clients).
4. If `Sec-Fetch-Site` is absent and `Origin` is present → allowed when `Origin` is trusted,
   or when the `Origin` is parsed and both its host **and** port (the scheme's default port
   when omitted) are equal to the host and port of the `Host` header. Otherwise rejected.
   `Origin: null`, or an `Origin` that cannot be parsed, is rejected.

Rejection: `403`, `{ "code": "FORBIDDEN", "message": "Cross-site request rejected" }`
(envelope in [errors.md](errors.md)). The check raises the rejection as a `ForbiddenException`
rendered by the shared error formatter; it never builds the response itself.

Because rule 1 lets them through unconditionally, `GET` and `HEAD` requests must **never
change state** on any endpoint; every state change uses `POST`, `PUT`, `PATCH` or `DELETE`.

### Parsing

- **`Origin` form:** `scheme://host[:port]`, nothing else.
  - The scheme must be `http` or `https`.
  - A host is required. Bracketed IPv6 hosts are accepted (e.g. `http://[::1]:8080`).
  - The port is optional. When omitted, it is the scheme's default port: `80` for `http`,
    `443` for `https`.
  - Any path (even a single `/`), query, fragment or user info makes the `Origin`
    unparseable.
- **Unparseable `Origin`:** `Origin: null`, or any value not of the form above, is never
  trusted (rule 2) and is rejected under rule 4.
- **`Host` comparison (rule 4):**
  - Hosts are compared ignoring case.
  - A `Host` without a port takes the default port of the `Origin`'s scheme (so
    `Origin: https://example.com` matches `Host: example.com` and `Host: example.com:443`).
  - Bracketed IPv6 `Host` values (`[::1]:8080`) are supported.
  - A missing or unparseable `Host` rejects the request.
- **`Sec-Fetch-Site`:** compared exactly and case-sensitively against `same-origin` and
  `none`; any other value (including `Same-Origin`) does not allow the request by itself.

Non-browser clients send neither `Origin` nor `Sec-Fetch-Site`, so server-to-server callers
of the public sending API (`POST /v1/messages` with a Bearer token) pass the check.

Browser callers of `POST /v1/messages` from another origin are **rejected** unless that
origin is trusted. This is intended: API tokens are meant for server-side callers and must
not live in browsers. The trusted-origins list must not be widened to allow such callers.

## Trusted origins and CORS

- The trusted-origins list is the same list that drives CORS: `ALLOWED_ORIGINS`
  (comma-separated bare origins, e.g. `https://admin.example.com`), falling back to the
  origin of `FRONTEND_BASE_URL`.
- `ALLOWED_ORIGINS` is split on `,`; each entry has its surrounding whitespace trimmed, and
  empty entries are dropped. Each entry must be a bare origin, otherwise boot is refused (see
  [architecture/configuration.md](../../architecture/configuration.md#origins)).
- When `ALLOWED_ORIGINS` has no entries, the list holds only the `scheme://host[:port]` part
  of `FRONTEND_BASE_URL` (its path, query and fragment are ignored), when it is set and
  parseable.
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

## Testing

Unit tests of the check cover every rule:

- `GET`, `HEAD` and `OPTIONS` pass without any header;
- `PUT` and an unknown method are checked like `POST`;
- `Sec-Fetch-Site: same-origin` and `none` pass; `cross-site` passes with a trusted `Origin`
  and is rejected with an untrusted one;
- neither `Sec-Fetch-Site` nor `Origin` passes;
- `Origin: null` is rejected, with and without `Sec-Fetch-Site: cross-site`;
- an unparseable `Origin` (path, trailing `/`, query, user info, non-`http(s)` scheme, no
  host) is rejected;
- `Host` and port matching: equal host and port pass; a different host or port is rejected;
  host case is ignored; default ports apply to both `Origin` and a port-less `Host`;
  bracketed IPv6 hosts match; a missing `Host` is rejected.

The trusted-origins list has unit tests for trimming, empty entries, the
`FRONTEND_BASE_URL` fallback (its `scheme://host[:port]` part only) and exact matching.

One integration test sends a cross-site `POST` through the front controller and gets the
`403` `FORBIDDEN` envelope.

## Pending decisions

None.
