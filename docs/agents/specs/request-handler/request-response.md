# Request and Response

Purpose: index of the specs defining the request and response models, the processing request (route params, attached data, early response), the error responses, and how a response is sent.

Part of the request flow indexed in [request-handler.md](../request-handler.md). The front controller ([front-controller.md](front-controller.md)) builds a `Request` from the PHP globals, wraps it in a processing request for one dispatch, and sends the resulting `Response` through the `ResponseSender`.

All classes live in `Pidgey\Http\` (except the wall clock, in `Pidgey\Infra\`). They are hand-written: no HTTP library and no new runtime dependency ([architecture/dependencies.md](../architecture/dependencies.md)).

| File | Content |
| --- | --- |
| [request.md](request-response/request.md) | The `Request`: method, path, query, header access, cookies, raw and JSON body, test overrides. |
| [processing-request.md](request-response/processing-request.md) | The processing request: route params, class-keyed attached data, the early-response contract. |
| [response.md](request-response/response.md) | `Response`, `JsonResponse`, `NoContentResponse`, `SetCookie` and the `ResponseSender`. |
| [errors.md](request-response/errors.md) | `ErrorResponse`, the `HttpException` family, the `ErrorFormatter` and the `WallClock`. |

## Why not copy Tent

The models follow Tent's `Request`, `ProcessingRequest`, `Response` and `ResponseSender`, with these differences:

- Headers are read only from `$_SERVER`, never `getallheaders()`, because `Authorization` reaches PHP through `CGIPassAuth` ([rewrite.md](rewrite.md#authorization-header)).
- The request can't be changed, apart from attachments and the early response.
- Attached data is keyed by class, not by string.
- Every error, including the 404 fallback, uses the JSON error envelope ([api/conventions/errors.md](../api/conventions/errors.md)), not plain text.

## Testing hooks

Every part can be tested without a web server:

- `Request` is built from the PHP globals by default; every value (`method`, `uri`, `headers`, `cookies`, `body`) can be overridden through constructor options ([request.md](request-response/request.md#test-overrides)).
- `ResponseSender` takes injectable emitters ([response.md](request-response/response.md#responsesender)).
- `ErrorFormatter` takes a `WallClock`, which tests freeze ([errors.md](request-response/errors.md#wall-clock)).

## Hand-offs to other parts of the request flow

- [routing.md](routing.md) (#59): the guaranteed fallback throws `NotFoundException` with the message `Cannot <METHOD> <path>`. A wrong method on a known path also answers `404`, never `405`. Routing fills the `param()` values.
- [middlewares.md](middlewares.md) (#61): the authenticated user and the API token should implement a small `Caller` interface with `organizationId()`, so scoped handlers use `require(Caller::class)`.
- [middlewares.md](middlewares.md) (#61): decide whether error responses built from exceptions also go through the response hooks, so a cross-origin admin UI gets CORS headers on `401`s.
- [middlewares.md](middlewares.md) (#61): guards throw for errors; `respond()` is only for non-error early answers ([processing-request.md](request-response/processing-request.md#early-response)).
- [front-controller.md](front-controller.md) (#62): wraps the whole dispatch, calls `ErrorFormatter` on any `Throwable`, sends through `ResponseSender`, and logs the real error behind a `500` through Monolog.
- [api/authentication/sessions.md](../api/authentication/sessions.md#clock): sessions use the same `WallClock`.
