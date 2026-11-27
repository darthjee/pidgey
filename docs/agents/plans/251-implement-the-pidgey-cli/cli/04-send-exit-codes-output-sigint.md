# Send, exit codes, output and SIGINT

**Send path in `run`:**
1. parse;
2. resolve the credentials;
3. read the body;
4. `createClient({ token, baseUrl, retryAmbiguous, allowInsecureHttp })`;
5. `send({ to, subject, text?, html? }, { signal, idempotencyKey? })`.

The client constructor can throw `CLIENT_CONFIGURATION` synchronously, and `run` handles that like a rejected send. `--idempotency-key` is forwarded as given.

Add `cli/ExitCode.js`, which maps an outcome to a code:
- success → `0`;
- `UsageError` or a `PidgeyError` with `CLIENT_CONFIGURATION` → `2`;
- a `PidgeyError` with `ABORTED` while `signal.aborted` (SIGINT) → `130`;
- any other `PidgeyError` → `1`.

Add `cli/Output.js`, which formats the output per the shared contracts:
- on success, the `--verbose` JSON line;
- on failure, the three stderr lines;
- for usage errors, `pidgey: <reason>`, plus the usage text only after a parse error.

**SIGINT in `bin/pidgey.js`:** create an `AbortController` and `process.once('SIGINT', () => controller.abort())`, then pass `controller.signal`. A SIGINT before the send (while stdin is read) also exits `130`. Set `process.exitCode` and let the process end naturally: the library leaves no keep-alive socket (no-hang). Do not call `process.exit()`, so stdout is flushed.

Add `specs/cli/ExitCode_spec.js`. Drive `run` in-process with a fake `createClient` that resolves or rejects with each kind of `PidgeyError`. Assert:
- each exit code;
- empty output on success, and the JSON line with `--verbose`;
- the stderr lines, including the SMTP reply and the possibly-sent notice (also on `ABORTED` with `possiblySent: true`);
- that the token, the message text and `error.body` never appear.

## Files to Change
- `clients/cli/cli/run.js`: the send path.
- `clients/cli/cli/ExitCode.js`, `clients/cli/cli/Output.js`: new.
- `clients/cli/bin/pidgey.js`: the SIGINT wiring.
- `clients/cli/specs/cli/ExitCode_spec.js`: new.
- `clients/cli/specs/cli/run_spec.js`: drop the cases that `ExitCode_spec.js` now covers, keeping no duplicates.
