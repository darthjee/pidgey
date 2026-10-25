# Conventions

Purpose: index of the shared rules that every API endpoint follows (the Citadel contract,
adapted to Pidgey).

The admin frontend was copied from Citadel and calls the Citadel API contract, so every
endpoint follows these rules. The public sending API (`POST /v1/messages`) follows them too,
except for its `/v1` path prefix and its Bearer-token authentication.

| File | Content |
| --- | --- |
| [format.md](conventions/format.md) | JSON content type, `camelCase`, dates, bodies on `DELETE`, the path rule, success codes, guard order. |
| [validation.md](conventions/validation.md) | Validation order, unknown fields dropped, all errors at once, message templates. |
| [errors.md](conventions/errors.md) | Error envelope, `error.smtp`, category and specific codes, status codes, "never `401` for a business rule". |
| [origin-check.md](conventions/origin-check.md) | Origin (CSRF) check, trusted origins and CORS. |
