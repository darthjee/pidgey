# ProcessingRequest attachments and UnauthorizedException
Implement attached data per `request-response/processing-request.md#attached-data`:

- **`attach(object $value): void`:** stores the value keyed by its class. Attaching a second object of the **same class** throws `LogicException`.
- **`attached(string $class): ?object`:** returns the single attached object that is an `instanceof $class` (a class or an interface), or `null`. More than one match throws `LogicException`.
- **`require(string $class): object`:** the same lookup, but a missing object throws `LogicException`. That answers `500` through the `ErrorFormatter`; check that `ErrorFormatter` maps a `LogicException` to `500 INTERNAL_ERROR`.
- Use `@template T of object` / `@param class-string<T>` / `@return T|null` docblocks, so static analysis infers the type.

Add **`Pidgey\Http\UnauthorizedException`** (`401`, code `UNAUTHORIZED`), following the existing `ForbiddenException` and `NotFoundException` pattern and the table in `request-response/errors.md`.

Tests:
- attach then require;
- `attached()` returns `null` when nothing is attached;
- duplicate attach throws;
- lookup by an interface works;
- two objects matching one interface throw;
- `require()` on a missing object throws `LogicException`;
- `UnauthorizedExceptionTest` covers the status and code;
- the `ErrorFormatter` output is the `401` envelope.

## Files to Change
- `source/lib/Http/ProcessingRequest.php` — `attach()`, `attached()`, `require()`
- `source/lib/Http/UnauthorizedException.php` — new
- `source/tests/unit/Http/ProcessingRequestTest.php` — extend
- `source/tests/unit/Http/UnauthorizedExceptionTest.php` — new
