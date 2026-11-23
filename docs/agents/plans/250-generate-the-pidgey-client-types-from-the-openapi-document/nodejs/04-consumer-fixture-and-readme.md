# Consumer fixture and README

- `specs/types/consumer.ts` is a strict TypeScript consumer of the installed package, positive usage only. It must:
  - import `PidgeyClient`, `PidgeyError` and `VERSION` from `'pidgey-client'`;
  - construct the client with every option: `token`, `baseUrl`, `allowInsecureHttp`, `maxRetries`, `retryDelayMs`, `retryAmbiguous`, `timeoutMs`;
  - call `send` with `{ to, subject, text }` and `{ signal: AbortSignal.timeout(1000), idempotencyKey }`;
  - read `status`, `smtp.code` and `replayed` from the result, assigned to explicitly typed variables (for example `const code: number = result.smtp.code`);
  - in a `catch`, narrow with `err instanceof PidgeyError` and read `code`, `possiblySent` and `smtp`.
- It is never executed, and Jasmine does not pick it up (it is not `*_spec.js`). ESLint does not lint `.ts`.
- README: a TypeScript section saying the package ships declarations generated from the `/v1` OpenAPI document, and a contributor note on `yarn generate:types` and `yarn build:types`.

## Files to Change

- `clients/nodejs/specs/types/consumer.ts`: new.
- `clients/nodejs/README.md`: TypeScript section.
