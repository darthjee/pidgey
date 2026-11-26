# Issue: Implement the pidgey CLI

## Description

- Parent epic: #219 (wave 2, created by #228).
- Specs: `docs/agents/specs/node-client/cli.md` (all sections) and `docs/agents/specs/node-client/testing.md` (section CLI, CLI subprocess tests, No-hang expectation).
- Owner: `cli`, with `product-owner` for the as-built docs.
- Depends on #248 (the full library behaviour) and #249 (the CLI package), both merged; #250 (shipped declarations) is merged too. Does not wait for #237. Blocks #252.
- Today, in `clients/cli/`:
  - `bin/pidgey.js` calls `run(argv, process)` from `cli/run.js`, which is synchronous and answers only `--help` and `--version`; anything else is a usage error (exit `2`).
  - `cli/Arguments.js` parses with `node:util` `parseArgs` in strict mode and rejects repeated flags through `tokens`.
  - `types/node.d.ts` is a hand-written shim for the few Node.js APIs used (`parseArgs`, `process`).
  - The specs are `specs/cli/Arguments_spec.js`, `run_spec.js`, `version_spec.js` and `specs/bin/Pidgey_spec.js`. There is no in-process HTTP server in the CLI specs yet.

## Problem

`bin/pidgey` only answers `--version` and `--help`; the command cannot send a message yet.

## Expected Behavior

- `pidgey` sends through the library and maps every result to the specified exit code (`0`, `1`, `2`, `130`) and output.
- The token never comes from a plain flag, and is never printed.
- Stdin above the 1 MiB cap is rejected without buffering it whole; SIGINT aborts the request and exits `130`.
- `--verbose` prints the success response as **one line of JSON** (`JSON.stringify` of what `send` resolves with: the success body plus `replayed`), so scripts can parse it; nothing else is printed on success.
- The process exits promptly after a send against a keep-alive server.
- Coverage stays at 100 %, subprocess spec included.

### Testing

- In-process specs, per `testing.md`, section CLI:
  - `specs/cli/Arguments_spec.js`: every flag, the token and URL sources and their precedence, every token-file rule (trim, 4 KiB, non-regular file, control characters, the `fstat` mode warning), `--token` rejected, `--idempotency-key`, `--allow-insecure-http`, repeated and unknown flags, missing token or URL;
  - `specs/cli/Body_spec.js`: body selection, the TTY case, unreadable stdin, and the stdin cap (exactly 1 MiB accepted, 1 MiB + 1 byte → exit `2` with nothing sent, reading stopped at the cap, counted in bytes);
  - `specs/cli/ExitCode_spec.js`: the exit-code mapping, nothing printed on success, the `--verbose` JSON line, and the stderr output on failure (`code`, `message`, SMTP reply, the `possibly sent` notice).
- `specs/bin/Pidgey_spec.js`: the real `bin/pidgey.js` spawned against an in-process server, only for what in-process specs cannot prove (the cases of its `testing.md` row, including SIGINT in flight and the no-hang case within 5 s).
- No message, token or URL userinfo appears in any output, asserted in the specs that exercise failures.

## Solution

1. **Structure** (owner `cli`):
   - `run` becomes async and takes its environment injected: `argv`, `env`, `stdin` (with `isTTY`), `stdout`, `stderr`, an `AbortSignal`, and the client factory. `bin/pidgey.js` wires the real `process`, sets `process.exitCode` from the result, and turns SIGINT into an abort of that signal.
   - The code stays in `cli/`, one module per concern, matching the spec files: `Arguments.js` (all flags), `Credentials.js` (token file and environment), `Body.js` (body selection and capped stdin), `ExitCode.js` and `Output.js` (mapping a result or error to an exit code and text).
2. **Flags, credentials, body, exit codes and output**: as in `cli.md`, sections Flags, Credentials and URL, Body and stdin, Exit codes, Output.
   - The usage text is printed only for parse errors (unknown, repeated or missing flags). Other usage errors (a bad token file, stdin over the cap, a TTY stdin, `CLIENT_CONFIGURATION`) print a one-line `pidgey: <reason>` only.
   - The token file is read from one descriptor (`open`, `fstat`, then `read` of at most 4 KiB + 1 bytes), so the regular-file and mode checks apply to what is actually read.
   - Stdin is read chunk by chunk and abandoned as soon as it goes over 1 MiB, without draining the rest.
3. **Types**: grow `clients/cli/types/node.d.ts` with only the Node.js APIs the CLI uses (the `fs` descriptor calls, `process.stdin`, `process.env`, `process.on('SIGINT')`). No `@types/node`.
4. **Test server**: the CLI specs get their own small in-process `node:http` server under `specs/support/`. It records requests, replies with a scripted status and body, can delay a reply, and can keep a keep-alive socket open. It does not import from `clients/nodejs/specs/`, because the library's specs are not part of its package.
5. **README** (owner `cli`): usage examples, the token sources, the exit codes, the `--verbose` output, and that a stdin that never closes blocks forever.
6. **As-built docs** (owner `product-owner`):
   - `docs/agents/clients/cli.md`: the modules, flags, specs and the test server;
   - `cli.md`, section Output, and the `--verbose` row of Flags: success prints one line of JSON.

Out of scope:
- `@types/node`;
- the integration spec and `clients_integration` (#252);
- a `PIDGEY_TOKEN_FILE` variable, a `--max-body-size` flag, a stdin timeout;
- the pack and publish jobs (#253).

## Benefits

- The CLI becomes usable from shell scripts and CI, with exit codes that tell a rejected send from a mistyped command.
- Scripts can read the result of a send through `--verbose` and `jq`.
- #252 can test the CLI end to end.
