# OpenAPI Endpoint

`GET /v1/openapi.json` serves the machine-readable contract of the public `/v1` API: an
OpenAPI 3.1 document. The narrative contract stays in [sending.md](sending.md) and
[conventions/errors.md](../conventions/errors.md); the document is the same contract in a form
that tools (type generation, contract tests, breaking-change diffs) can read.

## Relation to the conventions

The route is a documented exception to [conventions.md](../conventions.md), next to
`POST /v1/messages` ([sending.md](sending.md#relation-to-the-conventions)):

| Convention | `GET /v1/openapi.json` |
| --- | --- |
| Root-relative `*.json` path, no prefix | `/v1` prefix **and** a `.json` suffix: the **only** `/v1` path with a suffix ([format.md](../conventions/format.md#paths)) |
| Authentication required | **Public**: the only public `/v1` route ([authentication/api-tokens.md](../authentication/api-tokens.md#public-exception)) |

## `GET` and `HEAD /v1/openapi.json`

| Aspect | Rule |
| --- | --- |
| Authentication | None: no token, no scope, no gate. Credentials are ignored: a Bearer header, valid or not, and an `access_token` cookie are ignored and the document is served. |
| Methods | `GET` and `HEAD` only, each registered explicitly (the router never falls back from `HEAD` to `GET`, see [request-handler/routing.md](../../request-handler/routing.md#no-match)). |
| Success | `200`, `Content-Type: application/json`. |
| `HEAD` | The same status and headers as `GET`, with an empty body. |
| Other methods | The routing `404 NOT_FOUND` with the error envelope (`Cannot <METHOD> /v1/openapi.json`); never `405`, no `Allow` header. |
| Cache headers | None, as for `/health.json` ([format.md](../conventions/format.md#cache-headers)). |
| Origin check | Runs on state-changing methods only, so `GET` and `HEAD` are not affected; no difference from the rest of `/v1`. |
| CORS | No CORS of its own: the global `Cors` step applies as on the rest of `/v1` (a trusted origin is answered as usual, see [conventions/origin-check.md](../conventions/origin-check.md#trusted-origins-and-cors)). Cross-origin browser access from an untrusted origin (e.g. a hosted Swagger UI) is out of scope. |

The route is public, and no other `/v1` path or method becomes public with it: the match is
**exact**. `/v1/openapi.json/`, `/v1/openapi.jsonx` and `POST /v1/openapi.json` still get the
routing `404` or the API's own rules.

### Why public

The document reveals nothing beyond what the error envelope of `POST /v1/messages` and the
published client already reveal. Requiring a token would protect nothing secret and would
break tooling that reads the contract.

### The served file

- The body is the committed file `source/openapi/v1.json`, served **verbatim**. It is never
  generated at runtime from the router, so no deployment-specific data (hosts, organizations,
  build info) and no non-`/v1` route can get into it.
- It lives outside `source/public/`, so the web server never serves it directly; only the
  route does.
- The route reads a static file and touches no database.
- No route-specific rate limit: the global rate-limits pending item of
  [pending.md](../pending.md) applies.

### Content rules

- Version `3.1.0` of OpenAPI, valid against the official OpenAPI 3.1 meta-schema.
- Only `/v1` paths: `POST /v1/messages` and `GET`/`HEAD /v1/openapi.json`. No admin, `/auth`,
  operational or other non-`/v1` routes.
- No `servers` entry with a real host (omitted, or the relative `/`).
- `info.version` is the **contract** version (`1.0.0`), never the Pidgey release version, so
  the route does not fingerprint the running build.
- `POST /v1/messages` describes its request body, the `200` body (`{ status, smtp }`), the
  `Idempotency-Key` request header and the `Idempotent-Replayed` response header, and each
  status of its own errors table ([sending.md](sending.md#errors)), including the body-parser
  answers `400`, `413` and `415`; all errors use the envelope of
  [conventions/errors.md](../conventions/errors.md#envelope) (`error.code`, `error.message`,
  optional `error.details` and `error.smtp`).
- The routing `404` ([conventions/errors.md](../conventions/errors.md#generic-errors)) is **not**
  listed on each operation. `info.description` says instead that any unmatched path or method
  answers `404 NOT_FOUND` with the error envelope.
- The Bearer security scheme states the `send` scope and says nothing about how tokens are
  stored or hashed. `GET`/`HEAD /v1/openapi.json` declare no security (`security: []`).
- Examples use placeholder tokens (`pdg_example…`) and `example.com` hosts only, with no
  internal names, emails or IDs.

## `/v1` is additive-only

`/v1` stays backwards compatible, so the clients built on this document keep working:

- **Allowed** in `/v1`: new optional request fields, new response fields, new error codes.
- **Needs a `/v2`** (and a new major line of the clients): removing or renaming a field,
  changing a type or the meaning of a status or code, or making an optional field required.
- The rule is stated in `info.description` of the document as well.
- **The document is updated in the same change set as any `/v1` change**, so the document and
  these docs never disagree.
- A CI check that diffs the document against `main` and fails on a breaking change is planned
  (see the [spec](../../specs/node-client/openapi.md#v1-is-additive-only)); it is not part of
  the route.

## Edge cases

- Anonymous request, or a request with an invalid Bearer header or cookie: served normally.
- A valid Bearer token without scope `send`: served normally (no scope applies).
- `HEAD`: `200` with the headers of `GET` and an empty body.
- `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS` (not a CORS preflight): the routing `404`, as
  they follow the API's conventions. A CORS preflight is answered by the global `Cors` step
  before routing.
- `/v1/openapi.json/` and `/v1/openapi.jsonx`: the routing `404`.

## Testable rules

The implementation must cover at least:

- `GET` and `HEAD` answer `200` with no `Authorization` header, with a valid token and with
  an invalid one; the `Content-Type` is `application/json` and no cache header is sent;
- `HEAD` has the same status and headers as `GET` and an empty body;
- no other method or path becomes public: `POST /v1/openapi.json`, `/v1/openapi.json/` and
  `/v1/openapi.jsonx` still get the `404`; `POST /v1/messages` still needs its token;
- the served body equals the committed file;
- the committed file validates against the OpenAPI 3.1 meta-schema and follows the
  [content rules](#content-rules): only `/v1` paths, no real `servers` host, the contract
  `info.version`, placeholder examples only.

## Pending decisions

None. Rate limits and CORS for browser clients are tracked in [pending.md](../pending.md).
