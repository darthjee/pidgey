# Cli Plan: Implement the pidgey CLI

Main plan: [plan.md](plan.md)

## Shared contracts

What `cli` must produce:

- **`--verbose` output:** one line, `JSON.stringify(result) + '\n'`, of what `send` resolves with (`{ status, smtp, replayed }`). Without it, nothing is printed on success.
- **Failure output on stderr:**
  1. `pidgey: <code>: <message>`;
  2. then `pidgey: SMTP reply: <code> <message>` when `error.smtp` is present;
  3. then `pidgey: the message was possibly sent` when `possiblySent` is `true`.
  Never the message content, the token, the URL userinfo or `error.body`.
- **Usage text:** printed only after a parse error. Every other usage error is a single `pidgey: <reason>` line.
- **Exit codes:** `0`, `1`, `2` (`CLIENT_CONFIGURATION` included), `130`.
- **Required flags:** `--to` and `--subject`.
- **Modules:** `cli/Arguments.js`, `Credentials.js`, `Body.js`, `Output.js`, `ExitCode.js`, `run.js`. Specs as listed in `testing.md`, section CLI, plus `specs/support/TestServer.js`.
- **Limits:** the token file is at most 4096 bytes; stdin is at most 1048576 bytes.

## Steps

- [01 — Arguments and the async run](cli/01-arguments-and-async-run.md)
- [02 — Credentials and URL](cli/02-credentials-and-url.md)
- [03 — Body and capped stdin](cli/03-body-and-capped-stdin.md)
- [04 — Send, exit codes, output and SIGINT](cli/04-send-exit-codes-output-sigint.md)
- [05 — Test server and subprocess spec](cli/05-test-server-and-subprocess-spec.md)
- [06 — README and pack list](cli/06-readme-and-pack-list.md)

## CI Checks

- `clients/cli` and the smoke test: `make clients-checks`, or `docker compose run --rm clients clients/scripts/checks.sh` (CI job: `clients_checks`, on both Node.js images). It covers lint, typecheck, coverage at 100 % and the pack file list. Outside `checks.sh`, run `yarn build:types` in `clients/nodejs/` before the CLI's `yarn typecheck`.

## Notes

- **Thin layer:** no HTTP and no retry logic in the CLI. Option validation (the URL, `--idempotency-key`, cleartext HTTP) stays in the library and comes back as `CLIENT_CONFIGURATION`. The CLI only maps that code to exit `2`.
- **`--allow-insecure-http` message:** the library's message names the cleartext reason and not the URL, so it can be printed as is. A spec asserts that neither the URL userinfo nor the token appears in it.
- **Second SIGINT:** `bin` installs its handler with `process.once`. A second SIGINT then gets Node's default behaviour and kills the process at once, which is acceptable for an interactive user.
- **`node.d.ts`:** grow the shim only as far as the code needs. Do not add `@types/node`.
- **Coverage:** the platform check for the mode warning (`process.platform === 'win32'`) is reached through an injected `platform` value, not `/* c8 ignore */`.
