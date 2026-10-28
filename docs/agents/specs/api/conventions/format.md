# Format

Purpose: define the request/response format, the path rule, success codes and the guard
order shared by every API endpoint.

## Content type

- Requests and responses are JSON (`Content-Type: application/json`). The admin frontend
  sends `Content-Type: application/json` and `Accept: application/json` on every call, with
  `credentials: same-origin`, so the auth cookie goes along.
- Body-parser failures (malformed JSON, body too large, unsupported content type or charset)
  answer `400`, `413` or `415`; the error body is described in [errors.md](errors.md).
- Field names are **`camelCase`** (`refreshToken`, `isAdmin`, `newPassword`, `organizationId`…),
  in requests and responses alike. This replaces the former `snake_case` rule.
- Dates are ISO 8601 strings in UTC with milliseconds, e.g. `"2026-10-17T12:00:00.000Z"`.
- `DELETE` requests may carry a JSON **body** (e.g. `DELETE /auth/logoff.json`); the backend
  reads bodies on `DELETE`.

## Paths

- Every API path is **root-relative and ends in `.json`**, with no `/api` or `/v1` prefix:
  `/auth/login.json`, `/admin/users/search.json`, `/health.json`.
- **`*.json` always reaches the API**, anywhere in the path tree, including under `/admin/`.
  Non-`.json` paths under `/admin/` belong to the admin UI, which never ships `.json` files
  there (see [architecture/backend.md](../../architecture/backend.md) and
  [frontend/development.md](../../frontend/development.md)).
- **Exception:** the public sending API keeps its own `/v1` contract, `POST /v1/messages`
  (Bearer token with scopes, see [authentication.md](../authentication.md)).
- Many reads are `POST` on purpose: request bodies never go in query strings.
- Path params are written `:name` (e.g. `/smtp-connections/:id.json`), as in the route files
  (see [request-handler/routing.md](../../request-handler/routing.md#path-patterns)).

## Success codes

| Status | Use |
| --- | --- |
| `201` | Default for `POST`, including "read-like" `POST`s (status, search, poll…). |
| `200` | `GET`, `PATCH`, and the `POST`s whose endpoint spec says so (e.g. recover, reset-password, approve, deny). |
| `204` | No content (e.g. logoff). |

Clients treat any 2xx as success, so `200` and `201` are interchangeable for the admin
frontend; each endpoint spec still states its exact code, and the backend matches it.

## Guard order

Every request goes through the same steps, in this order:

1. **Origin check** for `POST`, `PUT`, `PATCH`, `DELETE`, including public routes (see
   [origin-check.md](origin-check.md)).
2. **Gate** on non-public routes: the caller must be authenticated (see
   [authentication.md](../authentication.md)).
3. **Admin check** on admin routes: the caller must be an instance admin (see
   [authentication.md](../authentication.md)).
4. **Body validation** (see [validation.md](validation.md)).
5. **Handler** (business logic).

Because guards run before validation, an anonymous request to a protected route with an
invalid body gets `401`, not `400`.

## Cache headers

No cache headers (`X-Skip-Cache`, `Cache-Control`) are required: Pidgey has no caching proxy
in front of the API. See the pending item in [pending.md](../pending.md).
