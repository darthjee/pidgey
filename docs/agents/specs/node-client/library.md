# Library (`pidgey-client`)

Spec of the Node.js library in `clients/nodejs/`. Errors, client-local codes and the retry
policy are in [errors-and-retry.md](errors-and-retry.md); packaging and the stack in
[packaging.md](packaging.md).

The library covers one operation, `POST /v1/messages`, as documented in
[api/endpoints/sending.md](../../api/endpoints/sending.md). It never calls
`GET /v1/openapi.json` at runtime.

## Usage

```js
import { PidgeyClient, PidgeyError } from 'pidgey-client';

const client = new PidgeyClient({
  token: process.env.PIDGEY_TOKEN, // e.g. pdg_example...
  baseUrl: 'https://pidgey.example.com',
});

const result = await client.send({
  to: 'user@example.com',
  subject: 'Hello',
  text: 'Hi!',
  html: '<p>Hi!</p>',
});
// result: { status: 'sent', smtp: { code: 250, message: 'OK: queued as 1a2b3c' } }

try {
  await client.send({ to: 'user@example.com', subject: 'Hello', text: 'Hi!' });
} catch (error) {
  if (error instanceof PidgeyError) {
    error.statusCode;   // 409
    error.code;         // 'NO_SMTP_CONNECTION'
    error.message;      // from the envelope
    error.details;      // validation details, when present
    error.smtp;         // { code, message } when the SMTP server replied
    error.possiblySent; // true when the message may have been delivered
  }
}
```

## Exports

| Export | Content |
| --- | --- |
| `PidgeyClient` | The client class. |
| `PidgeyError` | The single error class of every failure ([errors-and-retry.md](errors-and-retry.md#pidgeyerror)). |

Every public export carries accurate JSDoc, which references the OpenAPI-generated schema
types ([openapi.md](openapi.md#generated-types)).

## `new PidgeyClient(options)`

| Option | Required | Default | Rule |
| --- | --- | --- | --- |
| `token` | yes | — | Non-empty string; whitespace-only counts as empty. Sent as `Authorization: Bearer <token>`. |
| `baseUrl` | yes | — | Absolute URL with scheme `http` or `https`; a trailing slash is normalized away; a path prefix is kept. |
| `maxRetries` | no | `2` | Non-negative integer: at most `maxRetries + 1` attempts; `0` disables retrying. |
| `retryDelayMs` | no | `500` | Base delay of the exponential backoff ([errors-and-retry.md](errors-and-retry.md#backoff)). |
| `retryAmbiguous` | no | `false` | When `true`, failures that may have delivered the message are retried too, at the risk of a duplicate ([errors-and-retry.md](errors-and-retry.md#retryambiguous)). |
| `timeoutMs` | no | `30000` | Per-attempt timeout in milliseconds ([Timeout](#timeout)). |
| `allowInsecureHttp` | no | `false` | Allows an `http://` `baseUrl` on a non-loopback host; has no effect with `https://` ([Cleartext transport](#cleartext-transport)). |

Configuration mistakes reject with the client-local code `CLIENT_CONFIGURATION` **before any
request is made**:

- `token` absent, empty or whitespace-only: the client never sends an empty Bearer header;
- `baseUrl` absent, not absolute, without a scheme, or with a scheme other than `http` /
  `https`;
- an `http://` `baseUrl` on a non-loopback host without `allowInsecureHttp: true`
  ([Cleartext transport](#cleartext-transport));
- `maxRetries` negative or not an integer;
- an invalid `timeoutMs`.

`baseUrl` normalization: `https://pidgey.example.com`, `https://pidgey.example.com/`
both send to `https://pidgey.example.com/v1/messages`; a prefix is kept, so
`https://example.com/pidgey` and `https://example.com/pidgey/` both send to
`https://example.com/pidgey/v1/messages`.

### Cleartext transport

An `http://` `baseUrl` sends the Bearer token in clear text, so it is accepted **without
opt-in only for a literal loopback host**: `localhost`, any `127.0.0.0/8` address, or `[::1]`.

- The check runs on the hostname after WHATWG URL parsing, so `http://LOCALHOST` and
  `http://127.1` are exempt.
- There is no DNS lookup.
- **Not exempt:** `http://localhost.` (trailing dot), `*.localhost`, the IPv4-mapped
  `http://[::ffff:127.0.0.1]` and `http://0.0.0.0`.
- Any other `http://` host needs an explicit opt-in: `allowInsecureHttp: true`. Without it the
  client rejects with `CLIENT_CONFIGURATION` **before any request**. The message names the
  reason (cleartext token) and never the token.
- With an `https://` URL the option is accepted and has no effect.
- No warning is printed in any case.
- There is no environment variable for the opt-in; one can be added later without a breaking
  change.
- Redirects cannot downgrade the connection to cleartext: any 3xx is already
  `INVALID_RESPONSE` ([Redirects](#redirects)).

**Rejected:**

- Staying silent: it leaks the token on a mistyped production URL.
- Warning only: easy to ignore, and a library warning is noise for server consumers.
- Requiring the opt-in for every `http://` host, loopback included: friction with no gain,
  because loopback traffic never leaves the host.

## `client.send(message, options)`

Performs `POST /v1/messages` and returns a promise.

### Message

- The argument must be a plain object. No argument, `null`, an array or a non-object rejects
  with `CLIENT_CONFIGURATION`, and no request is made.
- The request body is built from a **whitelist of the v1 fields**: `to`, `subject`, `text`,
  `html` ([email/message-scope.md](../../email/message-scope.md#v1)). Any other key on the
  argument is not sent; supporting a new API field is a client release.
- Fields that are `undefined` are omitted from the body.
- The client does **not** validate the values: the API is the only validator (a
  `400 VALIDATION_FAILED` comes back as a `PidgeyError` with `details`), and duplicating its
  rules would drift.

### Request

| Item | Value |
| --- | --- |
| Method and path | `POST <baseUrl>/v1/messages` |
| `Authorization` | `Bearer <token>` |
| `Content-Type` | `application/json` |
| `Accept` | `application/json` |
| `Origin` | Not sent, so the API's [origin check](../../api/conventions/origin-check.md) passes for a server-side caller. |
| Redirects | Not followed (`redirect: 'error'`). |

Every attempt is a normal, independent request: the client caches nothing about the token
and does not pre-check the SMTP connection.

### Success

On `200` the promise resolves with the parsed body **exactly as returned**
(`{ status, smtp }`). The client does not reshape it, so a field added by the API reaches
the consumer without a client release. A `200` whose body lacks `smtp` is resolved unchanged:
the client does not require fields the API does not promise to keep.

A `200` (or other 2xx) whose body is not JSON rejects with `INVALID_RESPONSE`, is never
retried and is "possibly sent" ([errors-and-retry.md](errors-and-retry.md#client-local-codes)).

### Failure

Any other outcome rejects with a `PidgeyError`
([errors-and-retry.md](errors-and-retry.md#pidgeyerror)), after the retry policy has run
([errors-and-retry.md](errors-and-retry.md#retry-policy)).

## Redirects

Redirects are not followed (`redirect: 'error'`): a 3xx would turn the `POST` into a `GET`
and could carry the `Authorization` header to another host. Any 3xx rejects with
`INVALID_RESPONSE`, keeping the status, and is never retried.

## Abort

- `client.send(message, { signal })` accepts an optional caller `AbortSignal`.
- Aborting cancels the in-flight attempt **and** any pending backoff wait, and rejects with
  the client-local code `ABORTED`, which is never retried.
- A signal that is already aborted rejects with `ABORTED` before any request is made.
- Aborting during the backoff wait rejects at once, without another attempt.

## Timeout

- `timeoutMs` (default `30000`) bounds **each attempt**, not the whole call.
- A timed-out attempt is aborted and becomes an **ambiguous** `NETWORK_ERROR`
  (`possiblySent: true`): Pidgey may have sent the message before the response was lost. It
  is retried only with `retryAmbiguous: true`.

**Pending decision** (#231): connection reuse across sends (`fetch` keep-alive defaults), and whether the worst-case duration of a call (`timeoutMs` × attempts + backoff) needs an overall bound.

## Concurrency

Concurrent `send` calls on one client are independent: the client holds no per-call state.

## Runtime constraints

The library uses no Node-only API beyond `fetch` and `AbortSignal`, so a later browser target
(out of scope today) is possible without a rewrite. Runtime dependencies and the stack are in
[packaging.md](packaging.md#stack).
