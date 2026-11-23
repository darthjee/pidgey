# Shared fixtures and contract spec

Tie the specs and the client to the document.

- Add `ajv` and `ajv-formats` (exact pins) as devDependencies, and update `yarn.lock`. The document uses `format: date-time`.
- `specs/support/Fixtures.js`: export the valid response bodies the specs use:
  - the `200` success body;
  - one error envelope per documented status/code used by the specs (`400`, `401`, `403`, `409`, `422`, `429`, `500`, `502`, `503`, `504`, …).
  - Export each fixture with its HTTP status, so the contract spec can pick the schema.
- Switch `PidgeyClientSend_spec.js`, `PidgeyClientSendAbort_spec.js`, `RetryPolicy_spec.js` and `RetryPolicyAbort_spec.js` to these fixtures. Bodies that are off-contract on purpose stay inline, with names saying so: an extra field (`queueId`), a missing `smtp`, unknown codes, non-JSON bodies.
- `specs/contract/OpenApi_spec.js`:
  - Load the document. Use `new Ajv2020({ strict: false })` from `ajv/dist/2020` with `ajv-formats`, and add the document under an `$id`. `strict: false` tolerates OpenAPI keywords such as `example`.
  - Validate through `{ $ref: '<id>#/components/schemas/<Name>' }`.
  - **Request bodies:** for text only, html only, both, and with `idempotencyKey`, run `send` against `TestServer`, parse the recorded body, and validate it against `SendMessageRequest`. Also check that the `Idempotency-Key` header matches the document's header schema.
  - **Response fixtures:** validate each fixture against the schema the document gives its status in `POST /v1/messages` responses.
  - **Generated types:** regenerate in memory with the `openapi-typescript` Node API (`openapiTS` + `astToString`, the same options as the CLI script) and expect it to equal `types/v1.ts`. Make the failure message say to run `yarn generate:types`.
- The spec reads `../../source/openapi/v1.json`, resolved from the spec file's URL.

## Files to Change

- `clients/nodejs/package.json`, `clients/nodejs/yarn.lock`: `ajv`, `ajv-formats`.
- `clients/nodejs/specs/support/Fixtures.js`: new.
- `clients/nodejs/specs/contract/OpenApi_spec.js`: new.
- `clients/nodejs/specs/lib/PidgeyClientSend_spec.js`, `PidgeyClientSendAbort_spec.js`, `RetryPolicy_spec.js`, `RetryPolicyAbort_spec.js`: use the shared fixtures.
