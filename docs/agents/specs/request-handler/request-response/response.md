# Response

Purpose: define the response classes, the `SetCookie` value object, and how the `ResponseSender` sends a response.

Part of [request-response.md](../request-response.md). Error responses are covered in [errors.md](errors.md).

## Response classes

All in `Pidgey\Http\`.

### `Response`

- `body(): string`.
- `status(): int`, `200` by default.
- `headers(): list<string>`: header **lines** (e.g. `Content-Type: application/json`), not a name → value map, so several lines with the same name (e.g. `Set-Cookie`) can coexist.
- `addHeader(string $line)`: appends a line. It **rejects CR and LF**, which guards every header line against header injection, not only cookies.

### `JsonResponse extends Response`

- Constructor: `(array|JsonSerializable $data, int $status = 200)`.
- Encoded with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
- Adds `Content-Type: application/json`.
- **No key conversion:** handlers build `camelCase` keys themselves ([api/conventions/format.md](../../api/conventions/format.md)).

### `NoContentResponse`

- `204`, with no body and no `Content-Type` (e.g. logoff).

## `SetCookie`

PHP's `setcookie()` is **never** used: it writes the header directly, skips the `Response`, and can't be tested.

- A cookie is one more `Set-Cookie:` line in `Response::headers()`. The sender emits each line with `header($line, false)`, so several cookies survive.
- The **`SetCookie`** value object builds that line from name, value, `Path`, `Max-Age`, `Expires`, `HttpOnly`, `Secure` and `SameSite`.
- **`clear()`** builds the logout form: `access_token=; Path=/; Max-Age=0; Expires=<past date>`, plus the same attributes as in [api/authentication/access-token.md](../../api/authentication/access-token.md#cookie).
- It **rejects CR, LF, `;` and `,`** in names and values.

Reading cookies is covered in [request.md](request.md#cookies).

## `ResponseSender`

**`send(Response $response)`**:

1. calls the status emitter (`http_response_code()`) with `status()`;
2. calls the header emitter `header($line, false)` for each line of `headers()`;
3. echoes the body.

- The `false` (no replace) is deliberate: several `Set-Cookie` lines survive.
- Both emitters are **injectable callables**, so tests can see what was sent.
- The front controller ([front-controller.md](../front-controller.md#dispatch-pipeline)) sends every response through it, including error responses.
