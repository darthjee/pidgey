# Document the route and its exceptions

Write the as-built contract of `GET`/`HEAD /v1/openapi.json` in `docs/agents/api/`, as a new endpoint page linked from the endpoints hub. It covers:
- public access, with credentials ignored;
- the methods: `GET` and `HEAD`, where `HEAD` has no body and other methods get the routing `404`;
- the `200` `application/json` response with no cache headers;
- no CORS of its own: the global `Cors` step applies as on the rest of `/v1`;
- the committed file `source/openapi/v1.json`, which is served verbatim;
- the content rules.

Record the exceptions where the rules live:
- `conventions/format.md` and `endpoints/sending.md`: `/v1/openapi.json` is the one `/v1` path with a `.json` suffix;
- `authentication/api-tokens.md` (Disjoint credentials): the only public `/v1` route is `GET`/`HEAD` on exactly `/v1/openapi.json`. It's a route registered without `ApiTokenAuth`, not a prefix exception;
- `request-handler/routing.md`: the explicit `HEAD` route.

State the `/v1` additive-only rule in `docs/agents/api/`, with what is allowed and what needs a `/v2`. Also state that the document is updated in the same change set as any `/v1` change.

## Files to Change
- `docs/agents/api/endpoints/openapi.md` — new endpoint page.
- `docs/agents/api/endpoints.md` — hub row for the new page.
- `docs/agents/api/conventions/format.md` — the `.json` exception.
- `docs/agents/api/endpoints/sending.md` — the `.json` exception and the additive-only rule (or a link to it).
- `docs/agents/api/authentication/api-tokens.md` — the public exception.
- `docs/agents/request-handler/routing.md` — the `HEAD` route.
