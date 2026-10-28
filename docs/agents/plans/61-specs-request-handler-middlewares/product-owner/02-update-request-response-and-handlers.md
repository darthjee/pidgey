# Resolve the hand-offs in request-response and handlers

- **`request-handler/request-response/processing-request.md`:**
  - the `Caller` hand-off (Attached data): mark it as resolved — `Pidgey\Auth\Caller` with `organizationId()`, link [middlewares.md#caller-interface](../middlewares.md).
  - the error-response hand-off (Only for non-error answers): resolved — error responses skip the response hooks; CORS is a front-controller step (#62). Link `middlewares.md`.
- **`request-handler/request-response.md`** (Hand-offs section): mark the three #61 bullets as resolved with links into `middlewares.md` (`Caller`, errors/CORS, guards throw).
- **`request-handler/handlers.md`:**
  - line 7: the middleware interface is now specified in `middlewares.md` (drop "stays in ... (#61)" wording).
  - `handle()` "catches nothing" paragraph: replace "Whether error responses pass through the response hooks stays with #61" with the decision (they don't) and a link.
  - **Hook signatures:** point to [middlewares.md](middlewares.md) as the owner; keep the two signatures, drop "If #61 changes them".
  - line 78: link the `Middleware` interface section.
  - edge case 8: the infallible-response-hooks constraint is recorded in `middlewares.md` (link).
  - **Hand-offs**, the #61 bullet: mark it as resolved with links.

## Files to Change
- `docs/agents/specs/request-handler/request-response/processing-request.md`
- `docs/agents/specs/request-handler/request-response.md`
- `docs/agents/specs/request-handler/handlers.md`
