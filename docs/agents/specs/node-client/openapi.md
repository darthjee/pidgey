# OpenAPI

Spec of the machine-readable contract of `/v1`: the OpenAPI document, its public route, the
types and contract tests the library derives from it, and the rule that keeps `/v1`
backwards compatible.

## Document

- A single **OpenAPI 3.1** document describes the `/v1` surface:
  - `POST /v1/messages`: request body (`to`, `subject`, `text`, `html`), the `200` body
    (`{ status, smtp }`) and every documented error status
    ([api/endpoints/sending.md](../../api/endpoints/sending.md#errors));
  - the error envelope, with `error.details` and `error.smtp`
    ([api/conventions/errors.md](../../api/conventions/errors.md#envelope));
  - the Bearer security scheme with the `send` scope
    ([api/authentication/api-tokens.md](../../api/authentication/api-tokens.md)).
- It does **not** describe the admin API (the cookie-session routes).
- It is a static file in the backend source tree, owned by `backend`; its exact path is
  decided by its implementation sub-issue ([Static committed file](#static-committed-file)).
- [api/](../../api.md) stays the narrative contract. The document is updated in the same
  change set as any `/v1` change, so the two never disagree; the implementation sub-issue
  that adds the document also documents the route in `docs/agents/api/`.

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
| CORS | No CORS headers: cross-origin browser access (for example a hosted Swagger UI) is out of scope. |

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
- Its exact location in the backend tree is left to the implementation sub-issue.

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
- The rule is stated in `docs/agents/api/` and in the document (by the implementation
  sub-issue that adds the document).
- **Enforced in CI:** a backend CI check diffs the `/v1` document of the branch against
  `main` (for example with `oasdiff breaking`) and fails on any breaking change. It runs in
  the backend checks, so a `/v1` change is gated whether or not `clients/` is touched.

## Points to reconcile with the as-built docs

Transcribing the epic surfaced these differences with the current API docs. The as-built
docs win for current behaviour; the implementation sub-issue of the route settles them.

- **Path suffix.** [api/conventions/format.md](../../api/conventions/format.md#paths) states
  that `/v1` paths keep their own contract **without** the `.json` suffix
  ([api/endpoints/sending.md](../../api/endpoints/sending.md#relation-to-the-conventions));
  `GET /v1/openapi.json` carries one. The route is a documented exception to record there.
- **Public exception to the Bearer gate.** Every `/v1/*` request needs a Bearer API token
  ([api/authentication/api-tokens.md](../../api/authentication/api-tokens.md#disjoint-credentials)).
  The route's implementation sub-issue adds an explicit public exception there, limited to
  `GET` and `HEAD` on exactly `/v1/openapi.json` (an exact match, never a prefix), so no other
  `/v1` path or method becomes public.
- **`HEAD`.** The router never falls back from `HEAD` to `GET`
  ([request-handler/routing.md](../../request-handler/routing.md)): answering `HEAD` needs
  its own route. "Other methods follow the API's conventions" means the routing `404`
  `NOT_FOUND`, never `405`.
- **Documented statuses.** The error table of `POST /v1/messages` does not list the
  body-parser answers (`400 BAD_REQUEST`, `413`, `415`) nor the routing `404`, which
  [api/conventions/errors.md](../../api/conventions/errors.md#generic-errors) applies to
  every endpoint. "Every documented error status" in the document must state whether they
  are included.
