# Issue: JSON body parsing and validation

## Description
Part of epic #120 (auth API). The auth endpoints need JSON request bodies parsed and validated, with errors reported in the shared envelope (`specs/api/conventions/validation.md`, `specs/api/conventions/errors.md`) and the trimming rules of `specs/api/endpoints/auth.md#trimming`. The JSON parsing rules are already fully specified in `specs/request-handler/request-response/request.md#json-body`; body validation runs inside `process()` through a `Validator` (`specs/request-handler/handlers.md#body-validation`), whose API was left to this issue.

## Problem
- `Request` has no `body()` or `json()` yet, so no handler can read a request body.
- The exceptions the body parser and validation need do not exist: `BadRequestException` (400), `PayloadTooLargeException` (413), `UnsupportedMediaTypeException` (415) and `ValidationFailedException` (400 `VALIDATION_FAILED`).
- There is no `Validator`, and the specs leave open how missing, `null` and wrong-type fields are reported, what "valid email" means and how lengths are counted.
- `Request::path()` still does not reject a decoded NUL byte (pending note in `source/lib/Http/Request.php`), because `BadRequestException` was missing.

## Expected Behavior
### JSON body (`Request`)
- `body(): string` reads `php://input` once and caches it; a `body` constructor option replaces it in tests.
- `json(): array` is lazy and cached, and applies the rules of `request.md#json-body` in order:
  1. empty body → `[]`, whatever the `Content-Type`;
  2. a non-empty body must be `application/json` (case-insensitive), with an optional `charset` only when it is `utf-8` (case-insensitive); otherwise **415**;
  3. a 1 MiB limit, checked against `Content-Length` before reading and by capping the read at limit + 1 bytes; over the limit → **413**;
  4. `json_decode` with `JSON_THROW_ON_ERROR` and depth 64; malformed JSON or invalid UTF-8 → **400**;
  5. the top level must be a JSON object (checked on the decoded value, so `[]` is rejected); anything else → **400**;
  6. unknown fields are kept (validation drops them).
- Parser failures use the category code and the standard reason phrase (e.g. `BAD_REQUEST` / `Bad Request`).
- A decoded NUL byte in `path()` answers **400** (`BadRequestException`).

### Validation rules (decided)
- **Missing or wrong type:** a field that is absent, `null`, or `""` after trimming → `<field> should not be empty`. A non-string value (number, array, boolean) for a string field → `<field> must be a string`. In both cases **no further rules** run for that field, so it gets exactly one message.
- A field that passes its type and presence checks gets **every** failed rule reported, in the order the rules are declared.
- **Email:** `filter_var($value, FILTER_VALIDATE_EMAIL)` on the trimmed value → `<field> must be an email`. The max-length rule is checked separately. No IDN/Unicode local parts in v1.
- **Lengths:** "N characters" counts UTF-8 code points (`mb_strlen`); "N bytes" counts bytes (`strlen`). Invalid UTF-8 never reaches the validator, because `json_decode` has already rejected it.
- **Password rules:** min 8 characters, max 72 bytes, no NUL byte, never trimmed. Messages follow the templates of `validation.md`.
- **Trimming** is opt-in per field: `username`, `email` and `organizationName` are trimmed before validation ("not empty" is checked after trimming); passwords and tokens are taken as sent.
- Every field error is collected and thrown at once as `ValidationFailedException(list<string> $details)`: `400 VALIDATION_FAILED`, with the message being the details joined by `"; "`.

## Solution
- Add `BadRequestException`, `PayloadTooLargeException`, `UnsupportedMediaTypeException` and `ValidationFailedException` in `Pidgey\Http\`, as listed in `request-response/errors.md#exceptions`.
- Extend `Request` with `body()`, a lazy cached `json()` and the `body` test option, and make `path()` reject a decoded NUL byte.
- Add a small **fluent, per-field `Validator`** that the handler calls inside `process()`, for example `$v->string('username')->trim()->notEmpty()->maxLength(255)`, ending in `$v->validated()`. That call either returns only the declared fields (trimmed values), which drops unknown fields, or throws `ValidationFailedException`. Rules in v1: string, trim, notEmpty, email, minLength, maxLength, maxBytes, noNulByte.
- Out of scope: "one of a set", integer or other rules (added when an endpoint first needs them); the auth handlers themselves (later #120 sub-issues).
- Record the decided rules (missing/type handling, email, length counting, the `Validator` shape) in `specs/api/conventions/validation.md` and `specs/request-handler/handlers.md#body-validation`.
- Acceptance: unit tests for each parser rule (415, 413 with and without `Content-Length`, 400 malformed, 400 non-object, empty body), each validation rule and message, the one-message rule for missing and wrong-type fields, trimming, and the `400 VALIDATION_FAILED` envelope through `ErrorFormatter`.

## Benefits
- Every auth endpoint can read and validate its body the same way, with the spec's messages and envelope.
- The guard order holds: lazy parsing and validation inside `process()` keep `401` ahead of `400`.
- One message per missing field keeps responses short and predictable for the frontend.
- The pending NUL-byte path rule is closed.
