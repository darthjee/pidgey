# Plan: JSON body parsing and validation

Issue: [127-json-body-parsing-and-validation.md](../../issues/127-json-body-parsing-and-validation.md)

## Overview
Give handlers a way to read and validate JSON request bodies. `Request` gains `body()` and a lazy, cached `json()` that follow the already-specified parser rules (415 / 413 / 400), and `path()` starts answering 400 for a decoded NUL byte. The missing exceptions are added (`BadRequestException`, `PayloadTooLargeException`, `UnsupportedMediaTypeException`, `ValidationFailedException`). A small fluent `Validator`, called by handlers inside `process()`, collects every field error and throws `400 VALIDATION_FAILED`. The product owner first records the decisions taken on the issue in the specs; the backend agent then implements them.

## Agents involved

- [product-owner](product-owner.md)
- [backend](backend.md)

## Shared contracts

### Exceptions (`Pidgey\Http\`)
| Class | Status | Code | Message |
| --- | --- | --- | --- |
| `BadRequestException(?string $message = null)` | 400 | category (`BAD_REQUEST`) | given, or the reason phrase `Bad Request` |
| `PayloadTooLargeException` | 413 | category fallback `HTTP_413` (no 413 entry in the category table) | reason phrase (`Content Too Large`, already in `ErrorFormatter::REASON_PHRASES`) |
| `UnsupportedMediaTypeException` | 415 | category fallback `HTTP_415` | reason phrase `Unsupported Media Type` |
| `ValidationFailedException(list<string> $details)` | 400 | `VALIDATION_FAILED` | `implode('; ', $details)`; `details()` returns the list |

All extend `HttpException`, like `ForbiddenException` / `NotFoundException`. Parser failures carry **no** custom message: the envelope shows the category code and the reason phrase.

### `Request`
- New constructor option `body` (string) replacing `php://input`.
- `body(): string` — read once (capped at `MAX_BODY_BYTES + 1`), cached.
- `json(): array` — lazy, cached; rules in order: empty → `[]`; non-`application/json` media type or a `charset` other than `utf-8` (both case-insensitive) → 415; `Content-Length` > 1 MiB or read length > 1 MiB → 413; `json_decode(..., 512→64 depth, JSON_THROW_ON_ERROR)` failure → 400; decoded top level not an object → 400. Unknown fields kept.
- `MAX_BODY_BYTES = 1048576` (public constant so tests can reference it).
- `path()` throws `BadRequestException` when the decoded path contains `"\0"`.

### `Validator` (`Pidgey\Http\Validator`)
- Built from the decoded body: `new Validator(array $body)`.
- `string(string $field): FieldRules` declares a string field and returns a fluent builder with: `trim()`, `notEmpty()`, `email()`, `minLength(int)`, `maxLength(int)`, `maxBytes(int)`, `noNulByte()`. Each returns the builder.
- `validated(): array<string, string>` runs every declared field, returns only declared fields (trimmed when `trim()` was declared), or throws `ValidationFailedException` with every message.
- Per-field evaluation:
  1. value absent or `null` → `<field> should not be empty`, stop;
  2. not a string → `<field> must be a string`, stop;
  3. apply `trim()` if declared;
  4. `notEmpty()` declared and value `""` → `<field> should not be empty`, stop;
  5. otherwise every other declared rule in declaration order, each failure adding its message.
- Messages (templates of `validation.md`): `must be an email` (`filter_var(..., FILTER_VALIDATE_EMAIL)`), `must be longer than or equal to N characters` (`mb_strlen`), `must be shorter than or equal to N characters` (`mb_strlen`), `must be shorter than or equal to N bytes` (`strlen`), `must not contain a NUL byte`.
- Detail order: fields in declaration order, then rules in declaration order.
