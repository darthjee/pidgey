# Arguments and the async run

**Arguments.** Extend `cli/Arguments.js` to the full flag table of `cli.md`, section Flags:
- string flags: `--to`, `--subject`, `--text`, `--html`, `--token-file`, `--url`, `--idempotency-key`;
- boolean flags: `--retry-ambiguous`, `--allow-insecure-http`, `--verbose`, `--help`, `--version`.

Keep these rules:
- strict `parseArgs`, no positionals;
- a repeated flag rejected through `tokens`;
- `--token` stays an unknown flag.

`parseArguments` returns either `{ mode: 'help' }`, `{ mode: 'version' }` or `{ mode: 'send', … }`:
- `--help` wins over everything else, then `--version`;
- in send mode, `--to` and `--subject` are required: a missing one is a `UsageError`;
- `--text ''` is kept as an empty string, not dropped.

Mark parse errors so `run` knows to print the usage after them, for example a `showUsage: true` property on `UsageError`. Errors raised by the later modules use `UsageError` without it.

Update the `USAGE` text with every flag, the token sources and the exit codes.

**Async `run`.** Make `cli/run.js` an async `run(argv, io)` that resolves with the exit code. `io` holds:
- `env`;
- `stdin`, a readable with `isTTY`;
- `stdout` and `stderr`;
- `signal`, an `AbortSignal`;
- `createClient(options)`, which defaults to `new PidgeyClient(options)`;
- `platform`, which defaults to `process.platform`.

This step only wires `help` and `version`. Steps 02 to 04 add the send path. Update `bin/pidgey.js` to `process.exitCode = await run(...)`.

Update `run_spec.js` and `Arguments_spec.js`. The `Arguments_spec.js` cases are:
- every flag parsed;
- `--token` unknown;
- a repeated flag (including `--to a --to b`);
- `--idempotency-key` passed through;
- `--help` and `--version` precedence;
- a missing `--to` or `--subject`;
- the usage printed only for parse errors.

## Files to Change
- `clients/cli/cli/Arguments.js`: the full flag set, the send mode, required flags, the `showUsage` marker and the new `USAGE`.
- `clients/cli/cli/run.js`: async, with injected `io`.
- `clients/cli/bin/pidgey.js`: await `run` and pass the real process, an `AbortController` signal and the defaults.
- `clients/cli/specs/cli/Arguments_spec.js`, `clients/cli/specs/cli/run_spec.js`: the cases above.
- `clients/cli/types/node.d.ts`: `process.env`, `process.stdin`, `process.platform` and `process.once`.
