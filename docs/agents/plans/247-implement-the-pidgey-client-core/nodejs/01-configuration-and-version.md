# Configuration, cleartext rule and VERSION

Replace the stub constructor with validated configuration, and add the `VERSION` export.

- `lib/config.js` (new) exports:
  - `resolveConfig(options)`, which returns a plain frozen config `{ token, endpoint, allowInsecureHttp, maxRetries, retryDelayMs, retryAmbiguous, timeoutMs }`. `endpoint` is the normalized `<baseUrl without trailing slash>/v1/messages` (`url.pathname.replace(/\/+$/, '')`, keeping any prefix). Query and hash are dropped.
  - `isLoopbackHost(hostname)`: `localhost`, `/^127\.\d+\.\d+\.\d+$/` after WHATWG normalization (so `127.1` becomes `127.0.0.1`), and `[::1]`. It does not accept `localhost.`, `*.localhost`, `[::ffff:7f00:1]` (the parsed form of `[::ffff:127.0.0.1]`) or `0.0.0.0`.
  - Every failure throws `new PidgeyError(<reason>, { code: 'CLIENT_CONFIGURATION', possiblySent: false })`. The messages name the option and the rule. For the cleartext case the message says that `http://` would send the token in clear text and to pass `allowInsecureHttp: true`. No message ever includes the token or any part of the URL.
  - The validation rules are the options table of [plan.md](../plan.md#options-and-validation).
- `lib/PidgeyClient.js`: the constructor calls `resolveConfig` and keeps the result in private fields. It exposes no `options` property and no getter that returns the token.
- `lib/version.js` (new): `export const VERSION = '0.1.0';` with JSDoc saying it must equal `package.json`'s `version`. Re-export it from `lib/index.js`.
- Specs:
  - `specs/lib/config_spec.js` (or the constructor cases in `PidgeyClient_spec.js`) covers:
    - every rule and default;
    - the `baseUrl` normalization examples of `library.md`;
    - the loopback forms accepted without opt-in: `localhost`, `LOCALHOST`, `127.0.0.1`, `127.1`, `[::1]`;
    - the forms rejected without opt-in and accepted with it: `localhost.`, `foo.localhost`, `[::ffff:127.0.0.1]`, `0.0.0.0`, `example.com`;
    - the option ignored on `https://`;
    - messages never containing the token, or `user:pass` from `http://user:pass@example.com`.
  - `specs/lib/Version_spec.js` reads `package.json` with `node:fs` and asserts `VERSION` equals its `version`.
  - `specs/lib/index_spec.js` also asserts the `VERSION` export.

## Files to Change
- `clients/nodejs/lib/config.js` — new: option validation, normalization, loopback check.
- `clients/nodejs/lib/PidgeyClient.js` — the constructor uses `resolveConfig`, with private fields.
- `clients/nodejs/lib/version.js` — new: `VERSION`.
- `clients/nodejs/lib/index.js` — export `VERSION`.
- `clients/nodejs/specs/lib/config_spec.js` — new.
- `clients/nodejs/specs/lib/PidgeyClient_spec.js` — replace the `options` stub specs.
- `clients/nodejs/specs/lib/Version_spec.js` — new.
- `clients/nodejs/specs/lib/index_spec.js` — add `VERSION`.
