# Processing Request

Purpose: define the processing request: the per-dispatch wrapper around the `Request` that carries route params, class-keyed attached data and the early response.

Part of [request-response.md](../request-response.md). One processing request is built for each dispatch and wraps the [`Request`](request.md). It exposes the request's read methods unchanged. Apart from attachments and the early response, it can't be changed.

## Route params

**`param(string $name): ?string`** returns the value captured for a `:param` in the matched route, or `null`. Routing fills these values ([routing.md](../routing.md)). Route params are **not** attached data.

## Attached data

Middlewares attach typed objects to the processing request. In v1:

- the gate (cookie routes) attaches the authenticated user (`{ id, sid, username, isAdmin }` plus `organizationId`, see [api/authentication/gate.md](../../api/authentication/gate.md));
- the Bearer check (`/v1`) attaches the API token (`{ id, organizationId, scopes }`, see [api/authentication/api-tokens.md](../../api/authentication/api-tokens.md)).

The store is **keyed by class**:

- **`attach(object $value): void`** stores the object. Attaching a second object of the same class throws a `LogicException`, so nothing is overwritten silently and a later middleware can't replace the gate's user.
- **`attached(string $class): ?object`** returns the attached object that is an `instanceof $class` (a class or an interface), or `null`. If more than one attached object matches, it throws a `LogicException`.
- **`require(string $class): object`** does the same lookup, but a missing object throws a `LogicException`. That only happens on a misconfigured route (e.g. a handler that needs the user on a route with no gate), so it answers `500 INTERNAL_ERROR` ([errors.md](errors.md#errorformatter)), never `401`.

`Pidgey\Http\` does not depend on any auth class. Callers pass class names (`attached(AuthenticatedUser::class)`), so the return type reads clearly to the IDE and to reviewers.

**Rejected alternatives:**

- A generic string-keyed store (Tent's style): untyped, keys can be mistyped, and `mixed` everywhere.
- Typed accessors such as `user()` and `apiToken()`: `Pidgey\Http\` would depend on the auth models, and every new attachment would need new methods.

Organization scope needs an organization id from either the user or the token. Resolved in #61 ([middlewares.md](../middlewares.md#caller-interface)): both models implement `Pidgey\Auth\Caller` with `organizationId(): int`, at most one `Caller` is attached per request, and scoped handlers use `require(Caller::class)`.

## Early response

This spec owns the contract; [middlewares.md](../middlewares.md) (#61) uses it. Pidgey follows Tent (`RequestHandler::handleRequest`).

### Methods

- **`respond(Response $response): void`** answers early. Calling it when a response is already set throws a `LogicException`.
- **`hasResponse(): bool`** tells whether a response is set.
- **`response(): ?Response`** returns it, or `null`.

### Semantics once a response is set

- The dispatch loop checks `hasResponse()` after each request hook. No later request hook runs.
- The handler is not called.
- **All response hooks run**, in registration order, including those of middlewares whose request hook was skipped (Tent's behaviour).
- When nothing answers early, the handler's response goes through the same response hooks in the same order.

### Only for non-error answers

Early responses are for non-error answers, such as, later, a `304`. The CORS preflight is not one of them: it is answered by a front-controller step before routing ([front-controller.md](../front-controller.md#preflight)). Guard failures (`401`, `403`) **throw**, so every error goes through the single error formatter ([errors.md](errors.md#errorformatter)). This also keeps the gate's uniform `401` byte for byte the same.

**Rejected alternatives:**

- Onion order (only the response hooks of middlewares that ran, in reverse order).
- No response hooks after an early answer.

Error responses built from exceptions don't go through the middleware chain. Resolved in #61 ([middlewares.md](../middlewares.md#errors-cors-and-request-logging)): error responses **skip** the response hooks, and CORS is not a middleware but a front-controller step ([front-controller.md](../front-controller.md#cors-step-pidgeyhttpcors)) that runs before routing and on every final response, errors included. A cross-origin admin UI (`ALLOWED_ORIGINS`) therefore gets CORS headers on `401`s too.
