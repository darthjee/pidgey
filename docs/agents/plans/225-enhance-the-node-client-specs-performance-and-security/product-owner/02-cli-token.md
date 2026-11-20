# CLI token in cli.md and testing.md

Remove `--token` and add `--token-file` in `cli.md`:

- **Flags table:** delete the `--token` row; add `--token-file` | Path to a file holding the API token | `token` option; `PIDGEY_TOKEN` when absent.
- **Credentials and URL** section, rewritten:
  - Token sources in precedence order: `--token-file <path>`, then `PIDGEY_TOKEN`. An explicit `--token-file` wins over `PIDGEY_TOKEN`, as `--url` wins over `PIDGEY_URL`.
  - The CLI never takes the token from argv: arguments are readable by every local user (`ps`, `/proc/<pid>/cmdline`) and land in shell history. `--token` was removed before the first release (removing it later would be breaking).
  - File reading: the content with **one** trailing newline (`\n` or `\r\n`) trimmed, no other trimming. Missing or unreadable file, or a token empty / whitespace-only after trimming → exit `2`; the message names the path, never the content.
  - Permissions: on POSIX, a file readable by group or others (`mode & 0o077`) prints a warning to stderr and the send continues; no check on Windows.
  - Keep: missing token (no `--token-file`, no `PIDGEY_TOKEN`) or URL → exit `2`; the token is never read from stdin, never prompted for, never printed.
  - Rejected alternatives (short list): keeping `--token` with a warning; keeping it documented only; an interactive `/dev/tty` prompt; a config file / keychain with `pidgey login` (out of scope for `0.1.0`); a `PIDGEY_TOKEN_FILE` variable (addable later without a breaking change).
- Remove the #225 marker of that section.
- **Exit codes table, code `2`:** replace "missing token or URL in both flag and environment" with wording covering `--token-file` / `PIDGEY_TOKEN`, and add "unreadable or empty token file".
- Check the examples at the top of `cli.md`: they must not use `--token`.

In `testing.md`, update the `specs/cli/Arguments_spec.js` row: `--token-file` / `--url` versus `PIDGEY_TOKEN` / `PIDGEY_URL` precedence; trailing-newline trim; missing, unreadable and empty token file → exit `2`; the permission warning; `--token` rejected as an unknown flag.

Grep the whole `docs/agents/specs/` tree for any other `--token` mention (hub, `packaging.md` breaking-change table) and update it.

## Files to Change
- `docs/agents/specs/node-client/cli.md` — flags table, credentials section, exit code `2`, marker removed.
- `docs/agents/specs/node-client/testing.md` — `Arguments_spec.js` row.
- Any other file under `docs/agents/specs/` mentioning `--token`.
