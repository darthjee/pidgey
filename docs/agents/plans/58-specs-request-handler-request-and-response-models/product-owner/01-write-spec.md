# 01 — Write the request-response spec

Turn the stub into a folder index plus detail files, following the granularity rule in `docs/agents/specs.md`. Every file starts with a title and a `Purpose:` line, in English. Use **Pending decision** only for items the issue leaves open (none are expected).

- **`request-handler/request-response.md`** (index):
  - Keep the title and refine the `Purpose:` line if needed.
  - Add the "Part of the request flow indexed in [request-handler.md](../request-handler.md)" line, as `rewrite.md` does.
  - Add a table linking the four detail files.
  - Remove the Pending marker.
- **`request-handler/request-response/request.md`**: the `Request` (wraps the PHP globals) and its test overrides.
  - Method, path and query: `rawurldecode`, default `/`, `%00` → 400, raw `query()` + `queryParams()`.
  - The header access contract: `header($name, $default)`, `headers()` with lower-case keys that leave out `authorization`/`cookie`, the name-normalization rule with the `CONTENT_TYPE`/`CONTENT_LENGTH` exceptions, and the documented Apache limits.
  - Reading cookies (`cookie()`, array → default, documented PHP behaviour).
  - The raw body and lazy `json()`: empty → `[]`, media type and charset → 415, 1 MiB → 413, malformed or non-object → 400, depth 64.
- **`request-handler/request-response/processing-request.md`**:
  - wrapping the `Request` for one dispatch, with no changes to it otherwise;
  - `param()` for route params;
  - the class-keyed attached data (`attach`, `attached`, `require`, `instanceof` lookup, `LogicException` rules);
  - the early-response contract (`respond`, `hasResponse`, `response`; remaining request hooks and the handler skipped; all response hooks run in registration order; early answers only for non-error responses, guards throw).
- **`request-handler/request-response/response.md`**:
  - `Response` (`addHeader` rejects CR/LF), `JsonResponse` (flags, `Content-Type`, no key conversion) and `NoContentResponse`;
  - `SetCookie` (attributes, `clear()`, rejected characters, never `setcookie()`);
  - `ResponseSender` (status emitter, `header($line, false)` per line, body; injectable emitters).
- **`request-handler/request-response/errors.md`**:
  - `ErrorResponse` (the envelope of `api/conventions/errors.md`; omit null `details`/`smtp`);
  - the `HttpException` family and `ValidationFailedException`;
  - `ErrorFormatter` (category-code and reason-phrase fallbacks; any other `Throwable` → 500, logged through Monolog);
  - `WallClock` / `SystemWallClock`.
  - Link to `api/conventions/errors.md` for the envelope fields and codes instead of copying its tables.
- End the index or the relevant detail file with the cross-part notes from the issue's Hand-offs (#59 404 without 405, #61 `Caller` and CORS-on-errors, #62 dispatch wrapper), each as one line with a link to the sibling stub.

## Files to Change

- `docs/agents/specs/request-handler/request-response.md`: the stub becomes the folder index.
- `docs/agents/specs/request-handler/request-response/request.md`: new.
- `docs/agents/specs/request-handler/request-response/processing-request.md`: new.
- `docs/agents/specs/request-handler/request-response/response.md`: new.
- `docs/agents/specs/request-handler/request-response/errors.md`: new.
