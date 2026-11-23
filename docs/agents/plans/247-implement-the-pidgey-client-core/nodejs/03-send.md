# send: request, success and failure classification

Implement `client.send(message, { signal, idempotencyKey } = {})` as one attempt (`library.md`, sections `client.send`, Idempotency key, Success, Redirects, Abort, Timeout, Concurrency, Connections).

- **Before any request**, `send` rejects (as a rejected promise, never a sync throw) with `CLIENT_CONFIGURATION` in these cases:
  - `message` is not a plain object (`undefined`, `null`, an array, a primitive);
  - `idempotencyKey` is given and does not match `/^[\x21-\x7E]{1,255}$/` (the message never echoes the key).

  A `signal` that is already aborted rejects with `ABORTED`, `possiblySent: false`.
- **Body:** a whitelist of `to`, `subject`, `text` and `html`, with `undefined` values omitted, then `JSON.stringify`.
- **Headers:**
  - `Authorization: Bearer <token>`;
  - `Content-Type: application/json`;
  - `Accept: application/json`;
  - `Idempotency-Key: <idempotencyKey ?? crypto.randomUUID()>`, generated once per `send` call;
  - no `Origin`.
- **Fetch options:** `method: 'POST'`, `redirect: 'manual'`, and the combined signal.
  - Use `redirect: 'manual'` and turn any 3xx into `INVALID_RESPONSE` keeping the status. The spec's intent (never follow, keep the status) holds. `redirect: 'error'` would surface a 3xx as a network `TypeError` with no status, which the spec forbids ("keeping the status").
  - Write this in a code comment, and have `product-owner` note it in the as-built doc.
  - Spec: a server answering `302` with a `Location` gets no follow-up request, and the error carries `statusCode: 302`.
- **Success:** a 2xx with a JSON body resolves with the parsed object plus `replayed: response.headers.get('idempotent-replayed') === 'true'`. Do not reshape or require `smtp`. A 2xx with a non-JSON or oversized body → `INVALID_RESPONSE`, `possiblySent: true`.
- **Failures:**
  - mapping and `possiblySent` come from step 02;
  - a `fetch` rejection is classified with the cause-code rule in [nodejs.md](../nodejs.md#notes): unambiguous → `NETWORK_ERROR`, `possiblySent: false`; otherwise ambiguous;
  - the per-attempt timeout → ambiguous `NETWORK_ERROR`;
  - a caller abort after `fetch` was called and before the body was fully read → `ABORTED`, `possiblySent: true` (whatever the status);
  - always clear the timer.
- **Concurrency:** keep no per-call state on the instance. The attempt runs in its own function or private method, which #248 calls in a loop.
- `specs/lib/PidgeyClient_spec.js` and `specs/lib/PidgeyClientSend_spec.js` run against the `specs/support/` test server and cover:
  - every request item of the `library.md` Request table, including no `Origin`;
  - the whitelist, unknown keys and `undefined` omission;
  - the path with and without a prefix;
  - the passthrough of the success body, and a `200` without `smtp`;
  - `replayed` true/false;
  - a generated UUID key per call, different across two calls;
  - the caller key passed through, and invalid caller keys (empty, 256 characters, a space, a non-ASCII character) rejected with no request;
  - an invalid `send` argument with no request;
  - the 3xx not followed;
  - errors: refused (an unused port), reset after the request (the server destroys the socket) → ambiguous, timeout → ambiguous;
  - `ABORTED` before any request, in flight, and while reading the body of a `400` (`true`);
  - two concurrent sends independent;
  - an idle keep-alive server not hanging the suite.
- Every public method and class carries JSDoc, which `yarn typecheck` checks.

## Files to Change
- `clients/nodejs/lib/PidgeyClient.js` — `send` and the single-attempt method.
- `clients/nodejs/lib/request.js` — new (optional split): body whitelist, headers, key validation, network-error classifier.
- `clients/nodejs/specs/support/TestServer.js` — new: the scripted in-process `node:http` server.
- `clients/nodejs/specs/lib/PidgeyClientSend_spec.js` — new.
- `clients/nodejs/specs/lib/PidgeyClient_spec.js` — the request and header cases.
- `clients/nodejs/specs/lib/request_spec.js` — new, if `request.js` is split out (the classifier over synthetic causes).
- `clients/nodejs/eslint.config.mjs` — only if `specs/support/` needs its globals adjusted.
