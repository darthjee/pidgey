# Plan: Implement the pidgey CLI

Issue: [251-implement-the-pidgey-cli.md](../../issues/251-implement-the-pidgey-cli.md)

## Overview

Turn the `clients/cli` scaffold into the full `pidgey` command of `docs/agents/specs/node-client/cli.md`: flags, token file and environment, body and capped stdin, the send through `pidgey-client`, exit codes, output and SIGINT. `cli` builds it with one module per concern under `cli/` and an injectable async `run`. `product-owner` then records the as-built behaviour and fixes the `--verbose` output in the spec.

## Agents involved

- [cli](cli.md)
- [product-owner](product-owner.md)

## Shared contracts

- **`--verbose` success output:** exactly one line on stdout, `JSON.stringify(result) + '\n'`, where `result` is what `client.send` resolves with: the API's `SendMessageResponse` (`{ status, smtp: { code, message } }`) plus `replayed`. Without `--verbose`, stdout and stderr stay empty on success.
- **Failure output (stderr):** lines in this order:
  1. `pidgey: <code>: <message>`;
  2. `pidgey: SMTP reply: <smtp.code> <smtp.message>`, only when `error.smtp` is present;
  3. `pidgey: the message was possibly sent`, only when `possiblySent` is `true`, including on `ABORTED`.
  Nothing else: never the message content, the token, the URL userinfo or `error.body`.
- **Usage errors:**
  - a parse error (unknown, repeated, positional or missing required flag) prints `pidgey: <reason>` followed by the usage text;
  - any other usage error (a token-file problem, a missing token or URL, `CLIENT_CONFIGURATION`, unreadable stdin, stdin over the cap, a TTY stdin with no body flag) prints only `pidgey: <reason>`.
- **Exit codes:**
  - `0` sent;
  - `1` any `PidgeyError` except `CLIENT_CONFIGURATION`;
  - `2` any usage error, `CLIENT_CONFIGURATION` included;
  - `130` SIGINT.
- **Required flags:** `--to` and `--subject` (a missing one is a parse error, exit `2`).
- **Modules** in `clients/cli/cli/`: `Arguments.js`, `Credentials.js`, `Body.js`, `Output.js`, `ExitCode.js`, `run.js`, `version.js`. Specs: `specs/cli/Arguments_spec.js`, `Body_spec.js`, `ExitCode_spec.js` (plus the existing `run_spec.js` and `version_spec.js`), `specs/bin/Pidgey_spec.js`, and the server `specs/support/TestServer.js`.
- **Limits:** the token file is at most 4096 bytes; stdin is at most 1048576 bytes.
