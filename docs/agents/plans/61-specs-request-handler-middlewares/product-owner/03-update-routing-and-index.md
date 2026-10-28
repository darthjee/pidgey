# Update routing.md and the index

- **`request-handler/routing.md`**, Hand-offs, the #61 bullet: mark it as resolved.
  - Per-route middleware class names: link [middlewares.md](middlewares.md) (attachment section).
  - CORS preflight: replace the "If CORS is enabled later … global hook that runs before routing" text with: CORS (preflight and headers) is a front-controller step that runs before routing (#62, [front-controller.md](front-controller.md)); link `middlewares.md`.
  - Add the #62 hand-off note for CORS and request logging to the existing #62 bullet if it reads better there.
  - If `middlewares.md` states that `add()` rejects a middleware class also in the global list, mention it in the `add()` bullet (without inventing how the Router learns the global list; follow whatever step 01 recorded).
- **`request-handler.md`** (index): keep the `middlewares.md` row; make the flow comments consistent — request hooks (global then per route), response hooks skipped for errors, and CORS / request logging as front-controller steps.

## Files to Change
- `docs/agents/specs/request-handler/routing.md`
- `docs/agents/specs/request-handler.md`
