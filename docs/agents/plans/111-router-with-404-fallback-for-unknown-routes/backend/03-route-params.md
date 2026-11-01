# Route params on ProcessingRequest
Add route params per `request-response/processing-request.md#route-params`.

- `ProcessingRequest::__construct(Request $request, array $params = [])` (`array<string,string>`), and `param(string $name): ?string` returning the captured value or null. Params are immutable and are not attached data.
- Extend `ProcessingRequestTest` for `param()` with and without a value.

## Files to Change
- `source/lib/Http/ProcessingRequest.php` — params constructor argument and `param()`.
- `source/tests/unit/Http/ProcessingRequestTest.php` — new cases.
