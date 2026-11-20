# Cleartext transport

Replace the cleartext pending marker with the decision, in the library and in the CLI.

- **`library.md`, `new PidgeyClient(options)`:**
  - Add the `allowInsecureHttp` option row (optional, default `false`). It allows an `http://`
    `baseUrl` on a non-loopback host, and has no effect with `https://`.
  - Replace the "An `http://` `baseUrl` is accepted... no warning" paragraph and the
    `**Pending decision** (#231)` marker at line 81 with the rule:
    - `http://` without opt-in only for a literal loopback host: `localhost`,
      `127.0.0.0/8`, `[::1]`;
    - the check runs on the hostname after WHATWG URL parsing, so `LOCALHOST` and `127.1`
      are exempt;
    - no DNS lookup;
    - not exempt: a trailing-dot `localhost.`, `*.localhost`, IPv4-mapped
      `[::ffff:127.0.0.1]` and `0.0.0.0`;
    - otherwise `CLIENT_CONFIGURATION` before any request, with a message naming the
      cleartext reason and never the token;
    - no warning in any case;
    - redirects cannot downgrade the connection to cleartext (link to `#redirects`).
  - Add the cleartext case to the configuration-mistakes list.
  - Record the rejected alternatives (silent, warn only, opt-in for every `http://` host).
- **`errors-and-retry.md`:** add "a non-loopback `http://` `baseUrl` without
  `allowInsecureHttp`" to the `CLIENT_CONFIGURATION` row of the client-local codes table.
- **`cli.md`:**
  - Add the `--allow-insecure-http` flag row (boolean, maps to `allowInsecureHttp`).
  - Note that there is no environment variable for it (one can be added later without a
    breaking change).
  - The library's `CLIENT_CONFIGURATION` already maps to exit `2`. Mention a non-loopback
    `http://` URL without the flag as an example in the Exit codes `2` row.
- **`testing.md`:** extend the `PidgeyClient_spec.js` row:
  - the loopback forms accepted (`localhost`, `LOCALHOST`, `127.0.0.1`, `127.1`, `[::1]`);
  - the non-exempt forms rejected without opt-in;
  - opt-in accepted;
  - opt-in ignored with `https://`;
  - the message never containing the token.

  Extend the `Arguments_spec.js` row with `--allow-insecure-http` parsing and exit `2`
  without it.

## Files to Change
- `docs/agents/specs/node-client/library.md` — option row, cleartext rule, marker removed.
- `docs/agents/specs/node-client/errors-and-retry.md` — `CLIENT_CONFIGURATION` row.
- `docs/agents/specs/node-client/cli.md` — flag row, exit code `2` example.
- `docs/agents/specs/node-client/testing.md` — `PidgeyClient_spec.js` and `Arguments_spec.js` rows.
