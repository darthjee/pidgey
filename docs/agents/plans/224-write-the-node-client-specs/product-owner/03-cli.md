# CLI spec

Write `docs/agents/specs/node-client/cli.md` from #219's "CLI" section and CLI edge cases:
- Package `pidgey-cli`, command `pidgey`, flags only: `--to`, `--subject`, `--text`, `--html`, `--token`, `--url`, `--retry-ambiguous`, `--verbose`, `--help`, `--version`; `node:util` `parseArgs` strict mode.
- `PIDGEY_TOKEN` / `PIDGEY_URL` fallbacks (flag wins); token never read from stdin.
- Stdin rules (read as plain text when neither body flag is given and stdin is not a TTY; not read when `--html` is given; UTF-8 with replacement characters).
- Exit codes `0` / `1` / `2` / `130` with the full mapping (repeated flag, missing token/URL, `CLIENT_CONFIGURATION` → `2`; SIGINT aborts through the library `signal` → `130`).
- Output rules: nothing on success unless `--verbose`; on failure `code`, `message`, SMTP reply when present, "possibly sent" notice when `possiblySent`; never message content, token or SMTP secrets.
- The CLI holds no HTTP or retry logic.
- `**Pending decision** (#222)` for the library dependency range; `**Pending decision** (#225)` for token-on-command-line handling.

## Files to Change
- `docs/agents/specs/node-client/cli.md` — new.
