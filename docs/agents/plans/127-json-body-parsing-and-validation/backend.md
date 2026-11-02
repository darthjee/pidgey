# Backend Plan: JSON body parsing and validation

Main plan: [plan.md](plan.md)

## Shared contracts

See [plan.md](plan.md#shared-contracts): the four exceptions, the `Request` additions (`body` option, `body()`, `json()`, `MAX_BODY_BYTES`, NUL path rule) and the `Validator` contract. Follow the specs updated by the product owner (`api/conventions/validation.md`, `request-handler/handlers.md#body-validation`, `request-response/request.md#body`).

## Steps

- [01 — HTTP exceptions](backend/01-exceptions.md)
- [02 — `Request` body, JSON parsing and NUL path](backend/02-request-body-and-json.md)
- [03 — `Validator`](backend/03-validator.md)

## CI Checks
- `source`: `composer lint` (CI job: `backend_checks`)
- `source`: `composer coverage` with `PHP_ENV=test` (CI job: `backend_tests`); locally `make tests`

## Notes
- `Request` is a `final readonly class`. Lazy caching still works: leave the cache properties uninitialized and initialize them once from inside the class (readonly allows a single initialization from class scope); check with `isset()`/a sentinel. Don't drop `readonly` from the class.
- `path()` is called inside `FrontController::handle()`'s `try`, so the new `BadRequestException` becomes the JSON 400. Remove the "Pending: a decoded NUL byte…" note from the class docblock.
- `mbstring` ships with the `php:8.4` images; no composer `ext-` change expected, but add `ext-mbstring` to `composer.json` `require` if the lint/static setup asks for it.
- No handler uses the parser or `Validator` yet; the auth handlers come in later #120 sub-issues. Coverage comes from unit tests only.
