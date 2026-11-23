# Plan: Implement the pidgey-client core

Issue: [247-implement-the-pidgey-client-core.md](../../issues/247-implement-the-pidgey-client-core.md)

## Overview

This plan turns #246's stub classes into the working core of `pidgey-client`:
- configuration validation and the cleartext rule;
- `PidgeyError` with the full field set and the capped, redacted response body;
- one `send` attempt with the idempotency key, the success passthrough and a total failure classification, including `possiblySent`;
- the `VERSION` export.

No retry yet: #248 wraps the single attempt built here. `nodejs` builds the package; `product-owner` records the as-built behaviour and the validation rules decided in the issue.

## Agents involved

- [nodejs](nodejs.md)
- [product-owner](product-owner.md)

## Shared contracts

### Public surface (`lib/index.js`)

| Export | Shape |
| --- | --- |
| `PidgeyClient` | `new PidgeyClient(options)`, which throws `PidgeyError` `CLIENT_CONFIGURATION` synchronously. `client.send(message, { signal, idempotencyKey } = {})` returns `Promise<object>`. |
| `PidgeyError` | `extends Error`, `name = 'PidgeyError'`. The fields `code`, `message`, `statusCode?`, `details?`, `smtp?` and `possiblySent` (boolean) are enumerable. `body?` and `bodyTruncated?` are non-enumerable. |
| `VERSION` | A string literal equal to `package.json` `version` (`'0.1.0'`). |

### Options and validation

Every failure below throws `CLIENT_CONFIGURATION`, before any request is made.

| Option | Default | Valid |
| --- | --- | --- |
| `token` | — | A string with at least one non-whitespace character. |
| `baseUrl` | — | Parses with `new URL()`, and the protocol is `http:` or `https:`. |
| `allowInsecureHttp` | `false` | A boolean when given. An `http:` URL on a non-loopback host requires `true`. |
| `maxRetries` | `2` | `Number.isInteger(v) && v >= 0`. |
| `retryDelayMs` | `500` | `Number.isInteger(v) && v >= 0`. |
| `retryAmbiguous` | `false` | A boolean when given. |
| `timeoutMs` | `30000` | `Number.isInteger(v) && v > 0`. |

`undefined` means "use the default". The loopback hosts are the WHATWG `hostname` values `localhost`, any `127.x.x.x` dotted quad as normalized by URL parsing, and `[::1]`.

The per-call `idempotencyKey` must match `/^[\x21-\x7E]{1,255}$/`. Error messages never include the token, the URL or the key.

### Client-local codes

`CLIENT_CONFIGURATION`, `NETWORK_ERROR`, `INVALID_RESPONSE`, `ABORTED`. `possiblySent` follows `errors-and-retry.md`, Client-local codes and Ambiguous failures.

### Spec paths

The specs mirror `lib/` under `specs/lib/<Module>_spec.js`, plus `specs/lib/Version_spec.js`. `product-owner` writes these paths into the `testing.md` tables.
