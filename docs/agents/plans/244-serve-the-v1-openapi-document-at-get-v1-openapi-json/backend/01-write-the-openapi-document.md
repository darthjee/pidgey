# Write the OpenAPI document

Write `source/openapi/v1.json`, an OpenAPI 3.1 document of `/v1`, following [plan.md](../plan.md#shared-contracts) and `docs/agents/specs/node-client/openapi.md` (Document, Content rules). Take every field, header, status and code from `docs/agents/api/endpoints/sending.md`, `docs/agents/email/message-scope.md` (`to`, `subject` up to 255 characters, `text` and/or `html`) and `docs/agents/api/conventions/errors.md`:

- `POST /v1/messages`: the request body schema, the optional `Idempotency-Key` header (1 to 255 printable ASCII characters), the `200` body and the `Idempotent-Replayed: true` response header, and every status of the `sending.md` error table, including `400 BAD_REQUEST`, `413 HTTP_413`, `415 HTTP_415`, `422 IDEMPOTENCY_KEY_REUSED`/`VALIDATION_FAILED` and `409 IDEMPOTENCY_KEY_IN_USE`.
- `GET`/`HEAD /v1/openapi.json`, with `security: []`, answering `200` `application/json`.
- `components`: the error envelope (with `error.details` and `error.smtp`), the Bearer security scheme with the `send` scope, and shared schemas.
- `info.version` `1.0.0`. `info.description` carries the routing-`404` sentence and the additive-only rule.

## Files to Change
- `source/openapi/v1.json` — new: the committed `/v1` contract, served verbatim.
