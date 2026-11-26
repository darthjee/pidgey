# pidgey-client

The Node.js client for the `/v1` API of [Pidgey](https://github.com/darthjee/pidgey), an
email-delivery service. It is in development: `0.1.0` is the first release line. It sends one
email per `send` call and retries the failures that are safe to retry.

It has no runtime dependency: it only uses the global `fetch` and `AbortSignal`.

## Requirements

Node.js 22.12 or later.

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
// result: { status: 'sent', smtp: { code: 250, message: 'OK: queued as 1a2b3c' }, replayed: false }

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

// An optional caller key, to deduplicate two separate calls.
await client.send(
  { to: 'user@example.com', subject: 'Hello', text: 'Hi!' },
  { idempotencyKey: 'order-1234' },
);
```

Only `to`, `subject`, `text` and `html` are sent; any other key of the message is dropped. The
client does not validate their values: the API does, and its answer comes back as a
`PidgeyError` (for example `VALIDATION_FAILED`, with `details`).

On success `send` resolves with the body of the API exactly as returned, plus `replayed`:
`true` when the answer is a stored one replayed because of the same idempotency key (nothing
was sent by that call), `false` otherwise.

Every call sends an `Idempotency-Key`: yours, or a `crypto.randomUUID()` generated once per
`send` call. A key of your own must be 1 to 255 characters, each in `0x21`-`0x7E`.

## Options

`new PidgeyClient(options)`:

| Option | Required | Default | Rule |
| --- | --- | --- | --- |
| `token` | yes | none | Non-empty string; whitespace-only counts as empty. Sent as `Authorization: Bearer <token>`. |
| `baseUrl` | yes | none | Absolute URL with scheme `http` or `https`. A trailing slash is removed and a path prefix is kept: `https://example.com/pidgey/` sends to `https://example.com/pidgey/v1/messages`. |
| `allowInsecureHttp` | no | `false` | Allows an `http://` `baseUrl` on a non-loopback host. No effect with `https://`. |
| `timeoutMs` | no | `30000` | Per-attempt timeout in milliseconds; a positive integer. |
| `maxRetries` | no | `2` | Non-negative integer: retries after the first attempt, so a call makes at most `maxRetries + 1` attempts. `0` makes exactly one. |
| `retryDelayMs` | no | `500` | Non-negative integer: base of the exponential backoff, in milliseconds. Each wait is capped at `8000`. |
| `retryAmbiguous` | no | `false` | Boolean: also retry failures that may have delivered the message. Risks a duplicate email; see [Retries](#retries). |

An invalid option rejects with the code `CLIENT_CONFIGURATION` before any request, and the
message never contains the token. The token is kept in private fields, so
`console.log(client)` does not print it.

### Cleartext transport

An `http://` `baseUrl` sends the token in clear text, so it is accepted without opt-in only
for a literal loopback host: `localhost`, any `127.0.0.0/8` address or `[::1]`. Any other
`http://` host needs `allowInsecureHttp: true`; without it the constructor throws
`CLIENT_CONFIGURATION`. Redirects never downgrade the connection: they are not followed.

## Errors

Every failure of `send` is a `PidgeyError`, and so is a configuration mistake. It has:

| Field | Content |
| --- | --- |
| `code` | The `error.code` of the API envelope (`VALIDATION_FAILED`, `NO_SMTP_CONNECTION`, ...), or a client-local code. |
| `message` | Human readable description. It never contains the token. |
| `statusCode` | The HTTP status, absent when there was no HTTP response. |
| `details` | Validation details of the envelope, when present. |
| `smtp` | `{ code, message }` of the SMTP server, when it replied. |
| `possiblySent` | `true` when the message may have been delivered. |
| `body`, `bodyTruncated` | The response text (token redacted, cut to 4 KiB) and whether it was cut. Not enumerable, so they stay out of `JSON.stringify` and logs; read them as `error.body`. |

Client-local codes:

| Code | Meaning |
| --- | --- |
| `CLIENT_CONFIGURATION` | An invalid option, message argument, signal or idempotency key. No request was made. |
| `NETWORK_ERROR` | The request failed before a usable response, or timed out. |
| `INVALID_RESPONSE` | The answer is not what the API sends: a non-JSON or over 64 KiB body, or a redirect (a 3xx is never followed and its status is kept). |
| `ABORTED` | The caller's `signal` fired. |

New client-local codes may be added in a minor release, so handle an unknown `code`.

### `possiblySent`

`possiblySent` is `false` when the failure proves nothing was delivered (a validation error, a
refused connection, an unknown host, a TLS failure) and `true` when the message may have been
delivered although the call failed: a timeout, a reset connection, an SMTP timeout, an
unreadable `2xx` answer, or an abort after the request was dispatched. Treat `true` as "do not
resend without an idempotency key".

## Retries

`send` retries a failed attempt when the failure is transient, up to `maxRetries` times. The
decision uses the error `code`, never the SMTP reply.

Retried by default:

- `NETWORK_ERROR` when the request provably never reached Pidgey (DNS failure, refused
  connection, TLS failure before the request was written);
- `SMTP_CONNECTION_FAILED` and `SMTP_TEMPORARY_FAILURE`;
- an unknown `SMTP_*` code on `503`.

Never retried, whatever the options: validation and authorization failures (`400`, `401`,
`403`), `409` (including `IDEMPOTENCY_KEY_IN_USE`), `413`, `415`, `422` (including
`IDEMPOTENCY_KEY_REUSED`), `429`, `500`, `SMTP_REJECTED`, `SMTP_AUTH_FAILED`,
`SMTP_TLS_FAILED`, `SMTP_PROTOCOL_ERROR`, an unknown `SMTP_*` code on `502`, any other unknown
code, `INVALID_RESPONSE`, `CLIENT_CONFIGURATION` and `ABORTED`.

Failures that may have delivered the message (`possiblySent: true`) are not retried by
default, so the default client never causes a duplicate. They are an ambiguous
`NETWORK_ERROR` (a timeout, a connection reset after the request was written), `SMTP_TIMEOUT`
and an unknown `SMTP_*` code on `504`. `retryAmbiguous: true` retries them too, which can
send the same email twice: use it only when an occasional duplicate is better than a lost
send. `SMTP_PROTOCOL_ERROR` and `INVALID_RESPONSE` are never retried, even with it.

The wait before a retry is `retryDelayMs`, then twice that, and so on, each capped at 8000 ms.
The cap also applies to the first wait: `retryDelayMs: 10000` waits 8000 ms.

Every attempt of a call sends the same body and the same `Idempotency-Key`. When the retries
run out, the call rejects with the last error; its `possiblySent` is `true` if any attempt
was ambiguous, not only the last one.

With the defaults the worst case is about 91.5 s: three attempts of 30 s each plus the waits
of 0.5 s and 1 s. To bound the whole call, pass a signal of your own:
`send(message, { signal: AbortSignal.timeout(ms) })`. An abort is never retried and also
cancels the wait between two attempts.

## Abort and timeout

```js
const controller = new AbortController();
const pending = client.send(message, { signal: controller.signal });
controller.abort();
await pending; // rejects with code 'ABORTED'
```

A signal that is already aborted rejects with `ABORTED` and `possiblySent: false`. Aborted after
the request was dispatched, or during the wait that follows an ambiguous attempt, the call
rejects with `ABORTED` and `possiblySent: true`; aborted during the wait that follows only
unambiguous failures, it rejects with `possiblySent: false`.

`timeoutMs` bounds each attempt. To bound the whole call, pass a signal of your own:

```js
await client.send(message, { signal: AbortSignal.timeout(10_000) });
```

## Connections

Connections are reused through the dispatcher of the global `fetch`. The library does not
manage a pool: undici's `setGlobalDispatcher` tunes it for the whole process.

## TypeScript

The package ships type declarations, generated from the `/v1` OpenAPI document
(`source/openapi/v1.json`), so `send` takes a `SendMessageRequest` and resolves with a
`SendMessageResponse` plus `replayed`, and `PidgeyError` types `details` and `smtp` from the
document. No `@types` package is needed.

```ts
import { PidgeyClient, PidgeyError } from 'pidgey-client';

const client = new PidgeyClient({ token: process.env.PIDGEY_TOKEN!, baseUrl: 'https://pidgey.example.com' });
const result = await client.send({ to: 'user@example.com', subject: 'Hello', text: 'Hi!' });
const code: number = result.smtp.code;
```

For contributors: `types/v1.ts` is generated and committed. After a `/v1` change in
`source/openapi/v1.json`, run `yarn generate:types` and commit the result (a spec fails when it
is out of date). `yarn build:types` emits the `.d.ts` declarations into the git-ignored `dist/`,
which is what gets packed; run it before `npm pack`.

## Repository

<https://github.com/darthjee/pidgey> (the package lives in `clients/nodejs/`).

## License

MIT
