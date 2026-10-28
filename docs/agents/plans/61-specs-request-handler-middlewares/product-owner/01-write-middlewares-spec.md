# Write middlewares.md

Replace the stub `docs/agents/specs/request-handler/middlewares.md` with the full spec. Suggested sections:

1. **Title + `Purpose:`** — the middleware contract (request and response hooks), global and per-route attachment, order, and the concrete v1 middlewares by name and order only. One line placing it in the flow ([request-handler.md](../request-handler.md)), and a line saying it is structural only.
2. **`Pidgey\Http\Middleware`** — the interface: `public static function build(Container $container): static`, `processRequest(ProcessingRequest $request): void`, `processResponse(ProcessingRequest $request, Response $response): Response`. What each hook may do (attach, `respond()` for non-error answers, replace the response). Who runs them: `RequestHandler::handle()` ([handlers.md](handlers.md#requesthandler)); who builds them: `HandlerFactory::middlewares()`.
3. **`Pidgey\Http\BaseMiddleware`** — abstract, no-op hooks, `build()` returns `new static()`.
4. **Rejected alternatives** — onion `$next`; separate `RequestHook` / `ResponseHook` interfaces; route groups; middlewares declared on the handler class.
5. **Early answer** — link to [request-response/processing-request.md#early-response](request-response/processing-request.md#early-response); no pending item (all response hooks run, also after an early answer).
6. **Errors, CORS and request logging** — errors built from exceptions skip the response hooks; CORS (headers and preflight) and request logging are front-controller steps, not middlewares, with the reasons (preflight vs. routing order, `401`s must carry CORS headers, logs need the final status incl. 404s, the uniform `401` stays byte-identical). Rejected: re-running global response hooks on error responses. Hand-off to #62 ([front-controller.md](front-controller.md)).
7. **Attachment** — global (first, `OriginCheck` only in v1; a cross-site `POST` to an unknown path gets `404`, accepted) and per route (class names in `Router::add()`, [routing.md](routing.md)). Merge rule: global first, then the route's list in declared order.
8. **Concrete middlewares (name and order only)** — table: `OriginCheck` (global) → `Gate` / `ApiTokenAuth` → `RequireSendScope` → `AdminCheck`, each with its status and the spec that defines it. `AdminCheck` reads the gate's user with `require()`; without `Gate` it is a misconfiguration → `500`. `RequireScope` (abstract, `SCOPE` constant) with one subclass per scope; rejected: scope check in the handler.
9. **Order** — the guard order of [api/conventions/format.md#guard-order](../api/conventions/format.md#guard-order), with body validation inside the handler ([handlers.md](handlers.md)).
10. **`Caller` interface** — `Pidgey\Auth\Caller` with `organizationId(): int`, outside `Pidgey\Http\`; implemented by the authenticated user and the API token; at most one attached (disjoint credentials); scoped handlers use `require(Caller::class)`; link [organizations/scope.md](../organizations/scope.md).
11. **Security** — guards throw (`UnauthorizedException`, `ForbiddenException`), never `respond()`; hooks never catch; response hooks infallible in practice (headers only); the gate ignores Bearer, `ApiTokenAuth` ignores the cookie.
12. **Edge cases** — the four from the issue. For edge case 2 (a class in both the global and the route's list → `LogicException` from `add()`): state the rule as written; if the Router has no access to the global list, record where the global list comes from as a **Pending decision** rather than inventing one.
13. **Testing (for implementation issues)** — unit tests per middleware with a real `ProcessingRequest` from `Request` overrides plus fakes; the deny-by-default integration test over `config/routes.php` (public allowlist; `Gate` or `ApiTokenAuth` elsewhere; `AdminCheck` only after `Gate`).
14. **Hand-offs** — #62: CORS step and request-logging step.
15. **Pending decisions** — only what step 12 required, otherwise "None."


## Files to Change
- `docs/agents/specs/request-handler/middlewares.md` — full spec replacing the stub.
