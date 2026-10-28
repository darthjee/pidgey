# Write front-controller.md

Replace the stub in `docs/agents/specs/request-handler/front-controller.md` (keep the title and `Purpose:` line, drop the **Pending decision** stub).

## Sections
1. **Overview** — where it sits in the flow (link `../request-handler.md`), and that it replaces the placeholder `source/public/index.php`.
2. **`index.php` and `Pidgey\Http\FrontController`** — `index.php` is autoload + `FrontController::run()`; `handle(Request): Response` (no output, testable) and `static run(): void` (builds the `Request` from the globals inside the wrapper, then sends). Rejected: logic in `index.php`, Tent's static `RequestProcessor`.
3. **Bootstrap** — the five steps in order (autoload, logger, configuration validated eagerly, `Router` from `config/routes.php`, one `Container` per request with lazy PDO).
4. **Dispatch pipeline** — the ten numbered steps from the issue, linking `routing.md#dispatch-api`, `handlers.md#pidgeyhttphandlerfactory`, `request-response/errors.md#errorformatter`, `request-response/response.md#responsesender`.
5. **Error wrapper** — one `try/catch (Throwable)` over steps 1–6; CORS, logging and sending after it. Rejected: an error middleware.
6. **Global middlewares** — declared in `config/routes.php` via `new Router([OriginCheck::class])`; dispatcher prepends `Router::globalMiddlewares()` (details owned by `routing.md`).
7. **CORS step (`Pidgey\Http\Cors`)** — preflight definition, `204` for trusted and untrusted origins (headers only for trusted), fixed allowed methods/headers, non-preflight `OPTIONS` goes to routing, headers on every final response. Link `origin-check.md#trusted-origins-and-cors` for the matching rules; don't copy them.
8. **Request-logging step** — the one `info` line and its fields, what is never logged, the extra `error` log on `500`.
9. **Failures outside the wrapper** — invalid configuration → `500` envelope; logger/formatter/sender failure → hard-coded minimal `500` JSON + `error_log()`; `display_errors=0`.
10. **Edge cases**, **Testing**, **Performance and security**, **Backward compatibility** — as in the issue.
11. **Pending decisions** — `None.` (point to the log-level item in `architecture/configuration.md`).

## Files to Change
- `docs/agents/specs/request-handler/front-controller.md`
