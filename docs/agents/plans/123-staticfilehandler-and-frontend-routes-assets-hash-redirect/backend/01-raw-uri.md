# 01 — Raw URI accessor

The redirect must use `REQUEST_URI` exactly as received, never the decoded `path()`.

- `Request::uri(): string` returns the stored raw URI, or `/` when it is null.
- `ProcessingRequest::uri(): string` delegates to the request, like `path()` and `query()` do.
- Update the `Request` class docblock line that lists what is read.

## Tests
- `RequestTest`:
  - the raw URI is returned unchanged (`/foo%0D%0A?x=1`, `//evil.com`, `/foo/`);
  - a missing URI gives `/`.
- `ProcessingRequestTest`: `uri()` delegates.

## Files to Change
- `source/lib/Http/Request.php` — add `uri()`.
- `source/lib/Http/ProcessingRequest.php` — add `uri()`.
- `source/tests/unit/Http/RequestTest.php`, `source/tests/unit/Http/ProcessingRequestTest.php` — new cases.
