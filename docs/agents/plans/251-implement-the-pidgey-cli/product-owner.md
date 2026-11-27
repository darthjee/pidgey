# Product Owner Plan: Implement the pidgey CLI

Main plan: [plan.md](plan.md)

## Shared contracts

What `product-owner` documents, as built by `cli` (see [plan.md](plan.md#shared-contracts)):
- `--verbose` prints one line, `JSON.stringify` of the `send` result (`{ status, smtp, replayed }`);
- the three-line failure format on stderr;
- the usage text only after parse errors;
- `--to` and `--subject` are required;
- the module and spec layout under `clients/cli/`.

## Implementation Steps

### Step 1 — Spec text for `--verbose`
In `docs/agents/specs/node-client/cli.md`:
- the `--verbose` row of Flags becomes "Prints the result on success as one line of JSON ([Output](#output))";
- section Output: success with `--verbose` writes one line to stdout, `JSON.stringify` of what `send` resolves with (the API's success body plus `replayed`), so scripts can parse it. Fields the API adds later are printed too.

Also add the failure line format and the rule that the usage text is printed only after a parse error, if the section does not already say how the output is laid out.

### Step 2 — As-built docs
- `docs/agents/clients/cli.md`:
  - replace the scaffold description with the built command;
  - Surface: the flags, the token and URL sources, the body and stdin, the exit codes, the output;
  - Source layout: `Arguments.js`, `Credentials.js`, `Body.js`, `Output.js`, `ExitCode.js`, `run.js` with its injected `io`, and `bin/pidgey.js` with the SIGINT wiring;
  - Typings: the shim grew, and `@types/node` stays unadopted, so close or keep the pending decision as decided in #251;
  - Specs layout: the new specs and `specs/support/TestServer.js`;
  - Pack check: the new file list.
- `docs/agents/clients.md`:
  - the `clients/cli/` row: built, no longer a scaffold;
  - drop the "#251" item from the not-yet-built list.

Check the result against the merged code before writing. Only what was built goes in.

## Files to Change
- `docs/agents/specs/node-client/cli.md`: the `--verbose` row and section Output.
- `docs/agents/clients/cli.md`: as-built CLI.
- `docs/agents/clients.md`: the CLI row and the not-yet-built list.

## Notes
- This runs after the `cli` work, so the docs describe the code as merged.
