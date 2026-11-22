# OpenAPI

Spec of the machine-readable contract of `/v1`: the OpenAPI document, its public route, the
types and contract tests the library derives from it, and the rule that keeps `/v1`
backwards compatible.

## Document

- A single **OpenAPI 3.1** document describes the `/v1` surface:
  - `POST /v1/messages`: request body (`to`, `subject`, `text`, `html`), the `200` body
    (`{ status, smtp }`) and every documented error status
    ([api/endpoints/sending.md](../../api/endpoints/sending.md#errors));
  - the optional `Idempotency-Key` request header (1 to 255 printable ASCII characters), the
    `Idempotent-Replayed: true` response header, and the codes `422 IDEMPOTENCY_KEY_REUSED`
    and `409 IDEMPOTENCY_KEY_IN_USE`
    ([api/endpoints/sending.md](../../api/endpoints/sending.md#idempotency-key));
  - the error envelope, with `error.details` and `error.smtp`
    ([api/conventions/errors.md](../../api/conventions/errors.md#envelope));
  - the Bearer security scheme with the `send` scope
    ([api/authentication/api-tokens.md](../../api/authentication/api-tokens.md)).
- It does **not** describe the admin API (the cookie-session routes).
- It is a static file in the backend source tree, owned by `backend`, at
  **`source/openapi/v1.json`** ([Static committed file](#static-committed-file)).
- [api/](../../api.md) stays the narrative contract. The document is updated in the same
  change set as any `/v1` change, so the two never disagree. The route is documented in
  [api/endpoints/openapi.md](../../api/endpoints/openapi.md).

## `GET /v1/openapi.json`

The backend serves the document; it is the only backend addition of the epic.

| Aspect | Rule |
| --- | --- |
| Authentication | Public: no token, no scope ([Why public](#why-public)). Credentials are ignored: an invalid Bearer is served normally. |
| Methods | `GET` and `HEAD` only; other methods follow the API's conventions. |
| Response | `200`, `Content-Type: application/json`, always JSON (no YAML negotiation). |
| Content | The public contract only, under the [content rules](#content-rules). |
| Cache headers | None, following [api/conventions/format.md](../../api/conventions/format.md#cache-headers), like `/health.json`. |
| Origin check | No difference from the rest of `/v1`. |
| CORS | No CORS of its own (the global `Cors` step applies as on the rest of `/v1`): cross-origin browser access from an untrusted origin (for example a hosted Swagger UI) is out of scope. |

### Why public

The route stays public. The document reveals nothing beyond what the error envelope of
`POST /v1/messages` and the published npm package already reveal; requiring a token would
protect nothing secret and would break tooling that reads the contract.

### Static committed file

- The `/v1` document is a file committed in the repository and served **verbatim** by the
  route. The CI breaking-change diff ([`/v1` is additive-only](#v1-is-additive-only)) and the
  client's type generation ([Generated types](#generated-types)) read that same file.
- It is never generated at runtime from the router, so no deployment-specific data (hosts,
  organizations, build info) and no non-`/v1` route can get into it.
- It lives at `source/openapi/v1.json`, never under `source/public/` (the web server would
  serve it directly, bypassing the route).

### Content rules

A spec rule, enforced by the route's PHPUnit test ([testing.md](testing.md#cross-cutting)):

- only `/v1` paths: no admin, `/auth`, operational or other non-`/v1` routes;
- no `servers` entry with a real host (omitted, or the relative `/`);
- `info.version` is the **contract** version (for example `1.0.0`), never the Pidgey release
  version, so the route does not fingerprint the running build;
- examples use placeholder tokens (`pdg_example…`) and `example.com` hosts only, with no
  internal names, emails or IDs;
- the Bearer security scheme is described without any detail of how tokens are stored or
  hashed.

### Caching

No cache headers, as for `/health.json`
([api/endpoints/operational.md](../../api/endpoints/operational.md)): the client never
fetches the document at runtime, and the cache-headers pending item of
[api/pending.md](../../api/pending.md) covers any later change. **Rejected:**
`Cache-Control: no-cache` and `max-age` + `ETag` (an exception to the conventions with no
consumer).

### Load and abuse

- The route reads a static file and touches no database.
- Like the operational probes
  ([api/endpoints/operational.md](../../api/endpoints/operational.md#edge-cases)), it ignores
  credentials: an anonymous request or an invalid Bearer header is served normally.
- No route-specific rate limit: the global rate-limits pending item of
  [api/pending.md](../../api/pending.md) applies.

## Generated types

- `clients/nodejs/` generates its schema types from the document at **development time**
  (for example with `openapi-typescript` as a devDependency) into a committed `types/` file.
- The JSDoc of the public API references those types.
- The published package ships `.d.ts` declarations built from the JSDoc at pack time
  (`tsc --allowJs --declaration --emitDeclarationOnly`, a devDependency step). The source
  stays plain JavaScript and the library keeps zero runtime dependencies.
- The CLI consumes the library's types; it has no OpenAPI step of its own.
- Only the types are generated: generating the whole client (`openapi-generator` and
  similar) was rejected, because generated clients bring runtime dependencies and a generic
  shape, while the retry and error classification are hand-designed.

## Contract tests

Contract tests in `clients/nodejs/specs/` validate the request bodies the client builds, and
the response fixtures its specs use, against the document, and check that the committed
`types/` file is up to date with it. A drift between client and API fails the client's suite
([testing.md](testing.md#cross-cutting)).

The client never calls `GET /v1/openapi.json` at runtime, so servers older than that route
are supported.

## `/v1` is additive-only

- **Allowed** in `/v1`: new optional request fields, new response fields, new error codes.
- **Needs a `/v2`** and a new client major line: removing or renaming a field, changing a
  type or the meaning of a status or code, or making an optional field required.
- The rule is stated in [api/endpoints/openapi.md](../../api/endpoints/openapi.md#v1-is-additive-only)
  and in the document's `info.description`.
- **Enforced in CI:** a backend CI check diffs the `/v1` document of the branch against
  `main` (for example with `oasdiff breaking`) and fails on any breaking change. It runs in
  the backend checks, so a `/v1` change is gated whether or not `clients/` is touched.

## Points to reconcile with the as-built docs

**Settled** by the route's implementation (#244). Transcribing the epic surfaced these
differences with the API docs; each is now recorded where the rule lives, and the as-built
docs stay the source of truth.

- **Path suffix — settled.** `/v1` paths have no `.json` suffix, except
  `GET`/`HEAD /v1/openapi.json`, the one documented exception, recorded in
  [api/conventions/format.md](../../api/conventions/format.md#paths) and
  [api/endpoints/sending.md](../../api/endpoints/sending.md#relation-to-the-conventions).
- **Public exception to the Bearer gate — settled.** The only public `/v1` route is `GET` and
  `HEAD` on exactly `/v1/openapi.json`: a route registered without `ApiTokenAuth`, not a
  prefix exception, so no other `/v1` path or method becomes public. Recorded in
  [api/authentication/api-tokens.md](../../api/authentication/api-tokens.md#public-exception).
- **`HEAD` — settled.** The router never falls back from `HEAD` to `GET`, so the route is
  registered explicitly for both methods
  ([request-handler/routing.md](../../request-handler/routing.md#edge-cases)). "Other methods
  follow the API's conventions" means the routing `404 NOT_FOUND`, never `405`
  ([api/endpoints/openapi.md](../../api/endpoints/openapi.md#get-and-head-v1openapijson)).
- **Documented statuses — settled.** The document includes the body-parser statuses (`400`,
  `413`, `415`), matching the error table of
  [api/endpoints/sending.md](../../api/endpoints/sending.md#errors). Each operation lists only
  its own statuses; the routing `404` is **not** listed on each operation: a single sentence
  in `info.description` says any unmatched path or method answers `404 NOT_FOUND` with the
  error envelope ([content rules](../../api/endpoints/openapi.md#content-rules)).
