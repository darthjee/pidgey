# README and pack list

Document the usage in the package README, and update the expected pack list for the new `lib/` files.

- `clients/nodejs/README.md`:
  - drop "does not send email yet";
  - add the usage example of `library.md` (construct, `send`, catch `PidgeyError`, the optional `idempotencyKey`);
  - document:
    - the constructor options, with the defaults and the validation rules (the retry options marked as stored for the coming retry policy, without their behaviour; #248 writes that part);
    - the cleartext rule and `allowInsecureHttp`;
    - the `PidgeyError` fields and the client-local codes, noting that new codes may be added;
    - `possiblySent`;
    - abort with `{ signal }`, and bounding a whole call with `send(msg, { signal: AbortSignal.timeout(ms) })`;
    - that connections are reused through the global `fetch` dispatcher, and that undici's `setGlobalDispatcher` tunes this process-wide.
  - Placeholders only: a `pdg_example…` token and `example.com` hosts.
- `clients/nodejs/pack-files.txt`: regenerate with the command documented in `docs/agents/clients/nodejs.md`, Pack check, and check that the diff adds only the new `lib/*.js` files (`config.js`, `response.js`, `version.js`, and `request.js` if split). Nothing under `specs/` belongs in the list.
- Run `make clients-checks` and fix anything below 100 % coverage.

## Files to Change
- `clients/nodejs/README.md` — usage, options, errors, abort, connections.
- `clients/nodejs/pack-files.txt` — the new `lib/` files.
