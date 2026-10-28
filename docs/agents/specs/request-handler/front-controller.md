# Front Controller

Purpose: define what `source/public/index.php` does: bootstrap, dispatch, and error handling around the whole dispatch.

## Overview

Part of the request flow indexed in [request-handler.md](../request-handler.md). Apache hands every non-static request to `source/public/index.php` ([rewrite.md](rewrite.md)); the front controller turns it into a [`Request`](request-response/request.md), dispatches it and sends the final [`Response`](request-response/response.md).

```
Request → CORS preflight → routing → request hooks → handler → response hooks
        → errors → ErrorFormatter → CORS headers, request logging → ResponseSender
```

It ties together [request-response.md](request-response.md), [routing.md](routing.md), [handlers.md](handlers.md) and [middlewares.md](middlewares.md), and uses their classes without redefining them.

It **replaces the placeholder** `source/public/index.php`, which answers a static `{"status":"ok"}` on every path (see [Backward compatibility](#backward-compatibility)).

This file is structural only. What each endpoint does stays in [api/endpoints/](../api/endpoints/); what each guard checks stays in the api specs.

## `index.php` and `Pidgey\Http\FrontController`

`source/public/index.php` is a wiring file of about three lines: the Composer autoload, then `FrontController::run()`.

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

Pidgey\Http\FrontController::run();
```

Illustrative only; the autoload path is the implementer's call.

**`Pidgey\Http\FrontController`** holds the logic:

- **`handle(Request $request): Response`** — the whole [dispatch pipeline](#dispatch-pipeline). It produces **no output**, so it is unit-testable with `new Request([...])` ([request-response/request.md](request-response/request.md#test-overrides)).
- **`static run(): void`** — the production entry point. It builds the `Request` from the PHP globals **inside** the [error wrapper](#error-wrapper), calls the pipeline, and sends the final response.

Illustrative outline only:

```php
namespace Pidgey\Http;

final class FrontController
{
    public static function run(): void;

    public function handle(Request $request): Response;
}
```

Rejected:

- **Dispatch logic in `index.php`:** untestable without a web server.
- **Tent's static `RequestProcessor` / `Configuration`:** global state that every test must reset ([routing.md](routing.md#router)).

## Bootstrap

On every request, in this order:

1. **Composer autoload** (PSR-4 `Pidgey\` → `source/lib/`).
2. **Logger** (Monolog), first, so boot failures can be logged.
3. **Configuration** from the environment ([architecture/configuration.md](../architecture/configuration.md)), **validated eagerly on every request**. Invalid configuration is a [failure outside the wrapper](#failures-outside-the-wrapper).
4. **`Router`** built from `source/config/routes.php` ([routing.md](routing.md#route-files)), with the [global middleware list](#global-middlewares).
5. **One `Container` per request** ([handlers.md](handlers.md#pidgeycontainer)). Its services are lazy: PDO is opened only when a getter asks for it, so `/health.json` never touches the database ([api/endpoints/operational.md](../api/endpoints/operational.md#get-healthjson)).

## Dispatch pipeline

For one request:

1. **Build the `Request`.** It may throw `BadRequestException` on a decoded `%00` ([request-response/request.md](request-response/request.md#method-path-and-query)), so it runs inside the [error wrapper](#error-wrapper).
2. **CORS step, preflight part:** a preflight is answered here, before routing ([CORS step](#cors-step-pidgeyhttpcors)); the pipeline skips to step 8.
3. **Route match:** [`Router::match()`](routing.md#dispatch-api). No match → `NotFoundException('Cannot <METHOD> <path>')` ([routing.md](routing.md#no-match)).
4. **Build the processing request** with the captured params ([request-response/processing-request.md](request-response/processing-request.md#route-params)).
5. **Build the handler and the middlewares** with the [`HandlerFactory`](handlers.md#pidgeyhttphandlerfactory): the global list first, then the route's list ([middlewares.md](middlewares.md#merge-rule)).
6. **`RequestHandler::handle()`** ([handlers.md](handlers.md#requesthandler)): request hooks, handler (or early response), response hooks.
7. **Errors:** any `Throwable` from steps 1–6 → [`ErrorFormatter::format()`](request-response/errors.md#errorformatter) → `ErrorResponse`.
8. **CORS step, headers part:** adds the CORS headers to the final response, errors included.
9. **Request-logging step** on the final response ([Request-logging step](#request-logging-step)).
10. **Send:** [`ResponseSender::send()`](request-response/response.md#responsesender).

Steps 1 and 10 belong to `run()`; steps 2–9 are `handle()`.

## Error wrapper

- **One `try/catch (Throwable)`** around steps 1–6. The caught error goes to the single `ErrorFormatter`, which builds the envelope of [api/conventions/errors.md](../api/conventions/errors.md) and logs the real error behind a `500` ([request-response/errors.md](request-response/errors.md#errorformatter)).
- The CORS headers, the request logging and the sending run **after** the wrapper, on the **final** response, whichever path produced it (handler, early response, preflight or error).
- `RequestHandler::handle()` catches nothing ([handlers.md](handlers.md#errors-propagate)), and error responses skip the response hooks ([middlewares.md](middlewares.md#error-responses-skip-the-response-hooks)).

Rejected:

- **An error-handling middleware:** hooks can't wrap the handler, and error responses skip the response hooks ([middlewares.md](middlewares.md#errors-cors-and-request-logging)).

## Global middlewares

- The global middleware list is declared in `source/config/routes.php`, which builds the router with it:

  ```php
  $router = new Router([OriginCheck::class]);
  ```

  Illustrative only. In v1 the list is `[OriginCheck::class]` ([middlewares.md](middlewares.md#global)).
- The dispatcher reads it back with `Router::globalMiddlewares()` and prepends it to the route's list (step 5).
- The constructor, `globalMiddlewares()` and the duplicate check in `add()` are owned by [routing.md](routing.md#router).

## CORS step (`Pidgey\Http\Cors`)

CORS is a front-controller step, not a middleware ([middlewares.md](middlewares.md#cors-is-a-front-controller-step)). It lives in its own class, **`Pidgey\Http\Cors`**, unit-tested on its own.

Trusted origins, exact matching, the echoed origin, `Vary: Origin`, credentials and "never `*`" follow [api/conventions/origin-check.md](../api/conventions/origin-check.md#trusted-origins-and-cors); they are not redefined here.

### Preflight

- A **preflight** is an `OPTIONS` request with **both** an `Origin` and an `Access-Control-Request-Method` header.
- It is answered **`204`, before routing, for any path**:
  - **trusted origin** → `204` with `Access-Control-Allow-Origin` (the matched origin), `Access-Control-Allow-Credentials: true`, `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers` and `Vary: Origin`;
  - **untrusted origin, or CORS off** → `204` without CORS headers. The browser then blocks the real request.
- **Allowed methods and headers** are a fixed list:
  - `Access-Control-Allow-Methods: GET, POST, PATCH, DELETE`;
  - `Access-Control-Allow-Headers: Content-Type`.
- A **non-preflight `OPTIONS`** (missing either header) goes to routing, and gets `404` unless a route is registered for it ([routing.md](routing.md#no-match)).

### Headers on every final response

- Every final response (success, early answer or error) gets `Access-Control-Allow-Origin` (the matched origin) and `Access-Control-Allow-Credentials: true` when the request `Origin` is trusted, plus `Vary: Origin`.
- The step only **adds headers**: it never changes a status or a body, so the gate's uniform `401` stays byte-for-byte identical ([api/authentication/gate.md](../api/authentication/gate.md#uniform-401)).

## Request-logging step

Request logging is a front-controller step, not a middleware ([middlewares.md](middlewares.md#request-logging-is-a-front-controller-step)).

- **One line per request**, at `info`, on the final response, with:
  - the method;
  - the path, **without** the query string;
  - the final status;
  - the duration, measured with the monotonic `Pidgey\Infra\Clock`;
  - the matched route pattern, when a route matched.
- It covers every request: success, early answer, preflight, errors and routing `404`s.
- **Never logged:** headers, cookies, the body and the query string (redaction per [request-response/request.md](request-response/request.md#headers) and [api/authentication.md](../api/authentication.md)).
- A **`500`** additionally logs the real exception at `error` ([request-response/errors.md](request-response/errors.md#errorformatter)).
- The log level threshold is configured through Monolog; the log-level variable is still pending in [architecture/configuration.md](../architecture/configuration.md#pending-decisions).

## Failures outside the wrapper

- **Invalid configuration at boot** (e.g. a `SECRET_KEY` that refuses boot, see [architecture/configuration.md](../architecture/configuration.md#secret-keys)): logged, then answered with a `500 INTERNAL_ERROR` envelope ([api/conventions/errors.md](../api/conventions/errors.md)).
- **Logger, `ErrorFormatter` or sender failure:** a last-resort fallback answers a **hard-coded minimal `500` JSON body** and reports the failure with `error_log()`.
- **Never a PHP HTML error page:** `display_errors=0`.

## Edge cases

- **`HEAD`:** `404` unless a route is registered for it ([routing.md](routing.md#no-match)).
- **Output before `send()` is forbidden:** handlers and middlewares never `echo`; the body only leaves through `ResponseSender`.
- **`/`:** `404`. It is no longer the health check ([rewrite.md](rewrite.md#transition)).
- **Preflight to `/v1/*`:** gets the same CORS step. API origins are never trusted, so it answers `204` with no CORS headers.

## Testing

For the implementation issues; no web server is needed.

- **Unit tests for `FrontController::handle()`** with `new Request([...])` and a fake router and factory, covering:
  - routing `404`;
  - an `HttpException` → its envelope;
  - any other `Throwable` → `500` plus an `error` log;
  - CORS headers on error responses;
  - a preflight answered before routing (the router is not called);
  - exactly one log line per request, including `404` and `500`.
- **Unit tests for `Cors`.**
- **A boot test:** invalid configuration → `500` envelope.

## Performance and security

- **Per-request cost:** building the router with lazily compiled regexes ([routing.md](routing.md#performance-and-security)) and cheap configuration validation.
- **No stack trace and no exception message** of a non-`HttpException` reaches the response.
- **Untrusted origins are never echoed.**

## Backward compatibility

- Replaces the placeholder `index.php` (static `{"status":"ok"}` on every path).
- `/health.json` keeps the same body through a real route ([api/endpoints/operational.md](../api/endpoints/operational.md#get-healthjson)).
- `/` changes to `404`, as already noted in [rewrite.md](rewrite.md#transition).

## Pending decisions

None. The log-level variable stays pending in [architecture/configuration.md](../architecture/configuration.md#pending-decisions).
