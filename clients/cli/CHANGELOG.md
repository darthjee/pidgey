# Changelog

All notable changes to `pidgey-cli` are listed here, in the
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) style, with versions per the
[versioning rules](../../docs/agents/specs/node-client/packaging.md#versioning). A release
heading is the bare version, without brackets: `## X.Y.Z` or `## X.Y.Z - YYYY-MM-DD`.

## Unreleased

## 0.1.0

### Added

- Initial release of the `pidgey` command, which sends one message through
  `pidgey-client` `^0.1.0`. Requires Node.js `>=22.12.0`.
- Flags: `--to`, `--subject`, `--text`, `--html`, `--token-file`, `--url`, `--idempotency-key`,
  `--retry-ambiguous`, `--allow-insecure-http`, `--verbose`, `--help` and `--version`.
- The token comes from `--token-file`, otherwise from `PIDGEY_TOKEN` (there is no `--token`
  flag); the URL from `--url`, otherwise from `PIDGEY_URL`.
- Without `--text` and `--html`, the plain-text body is read from stdin (at most 1 MiB; a
  terminal stdin is refused).
- Exit codes: `0` sent, `1` send failed, `2` usage error, `130` interrupted by SIGINT.
