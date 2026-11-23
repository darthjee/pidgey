# Generated types and JSDoc

Generate the schema types from the `/v1` document, and type the public API with them.

- Add `openapi-typescript` (exact pin) as a devDependency, and update `yarn.lock`.
- Add the script `generate:types`: `openapi-typescript ../../source/openapi/v1.json -o types/v1.ts`. Run it and commit `types/v1.ts`.
- Reference the generated schemas from the public JSDoc, through `import('../types/v1.js').components['schemas'][…]` (nodenext resolves `.js` to the `.ts` file):
  - `send`'s `message`: `SendMessageRequest`;
  - its result: `SendMessageResponse & { replayed: boolean }`;
  - `PidgeyError`'s `smtp`: `SmtpReply`; `details` from `Error`.
  - Keep the runtime validation of `send`, which takes untrusted input. Where a body field is `unknown` for validation, keep a separate internal type, so the public signature is the generated one.
- `tsconfig.json` (typecheck): add `types` to `include` if needed, so `yarn typecheck` resolves the imports.

## Files to Change

- `clients/nodejs/package.json`: devDependency and `generate:types` script.
- `clients/nodejs/yarn.lock`: lockfile update.
- `clients/nodejs/types/v1.ts`: new, generated and committed.
- `clients/nodejs/lib/PidgeyClient.js`, `clients/nodejs/lib/PidgeyError.js`: JSDoc types.
- `clients/nodejs/tsconfig.json`: include, if needed.
