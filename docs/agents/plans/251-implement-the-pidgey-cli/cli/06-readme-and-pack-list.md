# README and pack list

**README.** Rewrite the `clients/cli/README.md` usage section with:
- the examples of `cli.md`;
- the flag list;
- the token sources (`--token-file`, `PIDGEY_TOKEN`; never a flag) and the token-file rules (4 KiB, one trailing newline trimmed, the mode warning);
- the URL sources;
- body and stdin, including the 1 MiB cap and that a stdin that never closes blocks forever;
- `--verbose`, which prints one JSON line;
- the failure output and the exit-code table.

**Pack list.** Add the new modules to `clients/cli/pack-files.txt`, keeping its sort order: `cli/Body.js`, `cli/Credentials.js`, `cli/ExitCode.js`, `cli/Output.js`. Specs and `specs/support/` are not packed.

Run `make clients-checks`: lint, typecheck, 100 % coverage, the pack list and the smoke test (its `pidgey --version` and `--help` assertions must still pass).

## Files to Change
- `clients/cli/README.md`: the full usage.
- `clients/cli/pack-files.txt`: the four new modules.
