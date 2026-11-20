# Product Owner Plan: API request size limits

Main plan: [plan.md](plan.md)

## Shared contracts

- **Body limit:** 1 MiB for every route, already enforced by the body parser ([request.md](../../request-handler/request-response/request.md#json-body)); over the limit → `413 HTTP_413` (`Content Too Large`).
- **Subject cap:** at most 255 UTF-8 characters → `400 VALIDATION_FAILED`, `subject must be shorter than or equal to 255 characters`.
- **`=?` rule:** printable-ASCII subjects containing `=?` are sent as encoded-words (implemented by `backend`).
- **`text` / `html`:** no field cap; bounded by the body limit; an oversized message still gets SMTP `552` → `SMTP_REJECTED`.
- **CLI stdin cap:** 1 MiB (1048576 bytes, before decoding).

## Steps

- [01 — API docs](product-owner/01-api-docs.md)
- [02 — Email message scope](product-owner/02-email-message-scope.md)
- [03 — Node client specs and consistency](product-owner/03-node-client-specs.md)

## Notes

- Docs only; no code. Write in the existing style of each file.
- The CLI is unreleased, so lowering its stdin cap is not a breaking change under packaging.md.
