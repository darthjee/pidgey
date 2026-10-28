# Write routing.md

Replace the stub's `**Pending decision:** content to be specified in #59` with the full spec. Keep the existing title and `Purpose:` line, and extend the purpose to mention the route files.

Suggested sections, all taken from the issue's Expected Behavior:

1. **Router.** A `Router` instance with no global state and no `reset()`.
   - `add(method, path, handlerClass, middlewares = [])` plus the `get`/`post`/`patch`/`delete` helpers.
   - The method is upper-cased when it is stored.
   - `is_subclass_of` check against the handler base class (#60).
   - Middleware class names are built only when the route matches.
   - A duplicate method + pattern throws `LogicException` (param names are ignored).
   - Lazy regex compilation. No named routes.
2. **Route files.** `source/config/routes.php` receives the `Router` and requires one file per area: `operational`, `auth`, `admin`, `organization` (covering the organization, API tokens and SMTP connections) and `v1`. The exact split is the implementer's call.
3. **Path patterns.**
   - Param names match `:[a-zA-Z_][a-zA-Z0-9_]*`, and the name ends at the first other character. Worked example: `/smtp-connections/:id.json` → param `id`. Mention the Tent bug this avoids.
   - Literals go through `preg_quote`. Params become `(?<name>[^/]+)`.
   - Anchored `#^…$#`, case-sensitive, matched against the decoded `Request::path()`.
   - A repeated param name throws `LogicException`.
4. **Matching and order.**
   - Strict registration order; first match wins.
   - The shadowing rule (literal before param, with the `/admin/users/search.json` example), caught by the routes-table test.
   - The query string is ignored.
5. **Param values.** Captured values are read through `param()` (link `request-response/processing-request.md#route-params`).
   - The router only checks `[^/]+`. Handlers throw `NotFoundException` for malformed values.
   - Integer-id helper hand-off to #60.
   - Rejected: `:id(\d+)` constraints.
6. **No match.**
   - A wrong method gets the same 404: `NOT_FOUND`, `Cannot <METHOD> <path>`, no 405, no `Allow` header.
   - No `HEAD`→`GET` fallback, and no automatic `OPTIONS`.
   - The dispatcher throws `NotFoundException`, and the wrapper around the dispatch (#62) formats it. There is no fallback handler class.
7. **Dispatch API.** `Router::match(Request): ?RouteMatch` (route, handler class, middleware classes, params). The processing request is built with the params, and the factory (#60) builds the handler.
8. **Edge cases.** Trailing slash and case are strict, the `/` path, the `/v1/messages` literal, and `/` never inside a param (`%2F` is blocked, see `rewrite.md#edge-cases`).
9. **Testing.** Unit tests for `Route`/`Router`, and the routes-table test (every endpoint in `api/endpoints/` resolves to its handler, and no route is shadowed).
10. **Performance and security.** Linear scan of about 35 routes, classes fixed at registration, no filesystem mapping, `%00` already rejected in `Request`.
11. **Hand-offs.**
    - #60: factory, integer-id helper, handler base class.
    - #61: per-route middlewares, and the CORS preflight question (routing runs before the hooks).
    - #62: builds the `Router` from `config/routes.php` and formats the no-match exception.
12. **Pending decisions:** none expected. Write "None" unless something is genuinely open.

Link to `api/conventions/format.md#paths`, `api/conventions/errors.md`, `request-response/errors.md` and `rewrite.md`.

## Files to Change
- `docs/agents/specs/request-handler/routing.md` — replace the stub with the full spec.
