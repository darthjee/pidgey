# Request

Purpose: define the `Request` model: how method, path, query, headers, cookies and body are read from the PHP globals, and how tests override them.

Part of [request-response.md](../request-response.md). The `Request` wraps the PHP globals for one HTTP request. It can't be changed: there is no Tent-style `setRequestPath()` or `setHeader()`, because Pidgey has no rewrite rules. Per-dispatch state (route params, attached data, early response) lives on the processing request ([processing-request.md](processing-request.md)).

## Test overrides

`Request` is built from the PHP globals by default. Every value can be overridden through constructor options, so tests need no web server:

| Option | Replaces |
| --- | --- |
| `method` | `$_SERVER['REQUEST_METHOD']` |
| `uri` | `$_SERVER['REQUEST_URI']` |
| `headers` | the request headers in `$_SERVER`, e.g. `['Content-Type' => 'application/json']` |
| `cookies` | `$_COOKIE` |
| `body` | `php://input` |

Header names in the `headers` option are normalized the same way as real ones, so `header()` and `headers()` behave the same as on a real request.

## Method, path and query

- **`method(): string`**: `$_SERVER['REQUEST_METHOD']`, upper-case.
- **`path(): string`**: `rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))`. Apache passes `REQUEST_URI` raw, so decoding is the `Request`'s job ([rewrite.md](../rewrite.md#edge-cases)).
  - Defaults to `/` when `REQUEST_URI` is missing or can't be parsed.
  - `rawurldecode` keeps `+` as is (`%2B` → `+`, `%20` → space).
  - `%2F` never arrives: Apache answers `404` itself (`AllowEncodedSlashes Off`, see [rewrite.md](../rewrite.md#edge-cases)).
  - A decoded NUL byte (`%00`) answers `400` (`BadRequestException`, see [errors.md](errors.md#exceptions)).
- **`query(): string`**: the raw query string, `''` when there is none.
- **`queryParams(): array`**: the `parse_str` output of `query()`. Request bodies never go in query strings ([api/conventions/format.md](../../api/conventions/format.md)), so this only matters for `GET`.

## Headers

Headers are read **only from `$_SERVER`**. `getallheaders()` is never used: `CGIPassAuth On` makes `Authorization` visible in `$_SERVER` ([rewrite.md](../rewrite.md#authorization-header)).

### `header(string $name, ?string $default = null): ?string`

- **Lookup ignores case.** The name is turned into a `$_SERVER` key: upper-case it, replace `-` with `_`, then add the `HTTP_` prefix.
  - Exceptions: `Content-Type` and `Content-Length` map to `CONTENT_TYPE` and `CONTENT_LENGTH`, with no prefix.
  - `header('content-type')`, `header('Content-Type')` and `header('CONTENT_TYPE')` all hit the same key.
- **Missing vs. empty.** A header that wasn't sent returns `$default` (`null` unless given). A header sent with an empty value returns `""`, never the default.
- **Raw value.** The value is returned exactly as received, with no trimming and no splitting. Apache joins a repeated header into one string with `, ` (`X-Dup: a` + `X-Dup: b` → `"a, b"`). A caller that needs a list splits it itself. Nothing in v1 needs that: the origin check, the gate and the body parser all read single-valued headers.
- **Host** is read with `header('Host')`. There is no `domain()` method; the origin check ([api/conventions/origin-check.md](../../api/conventions/origin-check.md)) parses host and port itself.

### `headers(): array<string, string>`

- Every request header, keyed by its lower-case, dash-separated name (`content-type`, `x-dup`), with values as returned by `header()`.
- Logging uses this map. It **leaves out `authorization` and `cookie`**, which must never be logged ([api/authentication.md](../../api/authentication.md)).

### Documented limits

Checked on `php:8.4-apache`:

- Header names containing `_` are dropped by Apache 2.4 and never arrive.
- The case of a header name is lost (`x-lower` → `HTTP_X_LOWER`).
- `Authorization` arrives only because of `CGIPassAuth` ([rewrite.md](../rewrite.md#authorization-header)).

## Cookies

### `cookie(string $name, ?string $default = null): ?string`

- Backed by `$_COOKIE`, PHP's own cookie parser.
- When the value is an **array** (e.g. `access_token[x]=…`), it returns `$default`, so an array never reaches the JWT code.
- The gate reads `cookie('access_token')` ([api/authentication/gate.md](../../api/authentication/gate.md)). `headers()` still leaves out `cookie`.

### Documented behaviour

Checked on `php:8.4-apache`:

- With a duplicate name, the first one wins (`access_token=A; access_token=B` → `A`).
- Values are percent-decoded (`a%3Db+c` → `a=b+c`; `+` is kept).
- PHP rewrites `.` and spaces in names to `_`, and `[` starts an array. Pidgey's cookie names (`access_token`, and the pending `__Host-access_token`) are not affected.
- Two separate `Cookie` headers are joined by Apache with `, `, and PHP splits only on `;`, so the second cookie is lost. Browsers never do this: HTTP/1.1 sends a single `Cookie` header, and `mod_http2` rejoins HTTP/2 cookie headers with `; `. A hand-made client that sends two headers just gets `401`.

**Rejected alternative:** parsing `HTTP_COOKIE` by hand. It avoids the renaming and the `,` problem, but it adds code to test and fixes nothing that matters for Pidgey's cookie names.

Setting cookies is covered in [response.md](response.md#setcookie).

## Body

### Raw body

**`body(): string`** reads `php://input` once and caches it. The `body` option replaces it in tests.

### JSON body

**`json(): array`** is **lazy**: it parses on the first call and caches the result. It therefore never runs before the guards, so an anonymous request with a broken body to a protected route still gets `401` ([guard order](../../api/conventions/format.md#guard-order)).

Parse failures throw an [`HttpException`](errors.md#exceptions). The error handling around the whole dispatch turns them into the error envelope, with the category code and the standard reason phrase ([api/conventions/errors.md](../../api/conventions/errors.md#generic-errors)).

Rules, in order:

1. **Empty body** (length 0): returns `[]`, whatever the `Content-Type`. Endpoints that send no body (e.g. `DELETE /auth/logoff.json`) just work.
2. **Media type:** a non-empty body must be `application/json`, compared without regard to case. Otherwise **415** (`UnsupportedMediaTypeException`).
   - A `charset` parameter is allowed only when it is `utf-8`, compared without regard to case. Any other charset also answers **415**.
3. **Size limit:** a fixed constant of **1 MiB**. A body over the limit answers **413** (`PayloadTooLargeException`).
   - It is checked against `Content-Length` before reading.
   - The read itself is capped at limit + 1 bytes, which covers bodies without a `Content-Length`.
4. **Malformed JSON or invalid UTF-8:** decoded with `json_decode`, `JSON_THROW_ON_ERROR` and a depth limit of **64**. A failure answers **400** (`BadRequestException`).
5. **Top level must be a JSON object.** A top-level array (including `[]`), string, number, boolean or `null` answers **400**. The check is made on the decoded value itself, because `[]` and `{}` look the same once decoded into a PHP array.
6. **Unknown fields** are kept. Dropping them is validation's job ([api/conventions/validation.md](../../api/conventions/validation.md)).

**Rejected alternatives:**

- A `BodyParser` middleware placed just before validation: routes without a body would need it switched off, and the parsed body would add to the attached data.
- Eager parsing in `Request`: it would answer `400` before `401` and break the guard order.
