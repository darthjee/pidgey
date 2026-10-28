# Error Responses

Purpose: define how errors become responses: the `ErrorResponse`, the `HttpException` family, the single `ErrorFormatter`, and the `WallClock` it uses for timestamps.

Part of [request-response.md](../request-response.md). The envelope fields, the category codes and the specific codes are defined in [api/conventions/errors.md](../../api/conventions/errors.md); this file does not copy them.

## `ErrorResponse extends JsonResponse`

Builds the envelope of [api/conventions/errors.md](../../api/conventions/errors.md#envelope) from `(status, code, message, ?details, ?smtp, timestamp)`.

- `error.details` and `error.smtp` are **omitted** when null.
- `statusCode` repeats the status.
- `timestamp` is ISO 8601 UTC with milliseconds.

See [response.md](response.md#jsonresponse-extends-response) for the JSON encoding.

## Exceptions

All in `Pidgey\Http\`.

- **`HttpException`** carries `(status, ?code, ?message, ?details, ?smtp)`.
- Subclasses per status:

  | Class | Status |
  | --- | --- |
  | `BadRequestException` | 400 |
  | `UnauthorizedException` | 401 |
  | `ForbiddenException` | 403 |
  | `NotFoundException` | 404 |
  | `ConflictException` | 409 |
  | `PayloadTooLargeException` | 413 |
  | `UnsupportedMediaTypeException` | 415 |
  | `UnprocessableEntityException` | 422 |
  | `LockedException` | 423 |

- **`ValidationFailedException(list<string> $details)`**: `400`, `VALIDATION_FAILED`, with the message being `details` joined by `"; "` ([api/conventions/errors.md](../../api/conventions/errors.md#validation-errors)).
- A specific code (e.g. `USERNAME_TAKEN`, `USER_DENIED`) is passed to the constructor.
- The SMTP failures (`502`, `503`, `504`, see [email/failures.md](../../email/failures.md)) use `HttpException` directly with their codes. Sanitizing `error.smtp` stays with the email layer.

## `ErrorFormatter`

The single error formatter: **`format(Throwable $error): ErrorResponse`**.

- **`HttpException`:** uses its fields.
  - A missing code falls back to the category code from the [category codes table](../../api/conventions/errors.md#category-codes), or `HTTP_<status>` for any other status.
  - A missing message falls back to the standard reason phrase (e.g. `Bad Request`). The body-parser failures (`400`, `413`, `415`, see [request.md](request.md#json-body)) rely on this.
- **Any other `Throwable`** (including the `LogicException`s from the attached-data API, see [processing-request.md](processing-request.md#attached-data)): `500 INTERNAL_ERROR` / `Internal server error`. The real error is logged through Monolog and never sent.
- **Who calls it:** the front controller ([front-controller.md](../front-controller.md), #62), around the whole dispatch.

## Wall clock

The existing `Pidgey\Infra\Clock` is monotonic (`hrtime()`), meant for timeouts, and can't produce a wall-clock `timestamp`.

- A new **`Pidgey\Infra\WallClock`** interface: `now(): DateTimeImmutable`, in UTC. `SystemWallClock` is the implementation.
- `ErrorFormatter` gets it injected, so tests can freeze the time.
- Sessions ([api/authentication/sessions.md](../../api/authentication/sessions.md#clock)) use it too.

**Rejected alternatives:**

- Adding `nowUtc()` to `Clock`: it mixes monotonic and wall time.
- `psr/clock`: a new runtime dependency ([architecture/dependencies.md](../../architecture/dependencies.md)).
