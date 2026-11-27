# Cli Plan: Release pidgey-client and pidgey-cli 0.1.0

Main plan: [plan.md](plan.md)

## Shared contracts

- Add a bare `## 0.1.0` heading below the empty `## Unreleased` in `clients/cli/CHANGELOG.md`, matching the gate regex `^## 0\.1\.0( |$)` of `clients/scripts/release_pack.sh`.
- `clients/cli/package.json` stays at `0.1.0` with `"pidgey-client": "file:../nodejs"`. The published `^0.1.0` range comes from the staging-copy pack, so do not hand-edit a floor.

## Implementation Steps

### Step 1 — Add the 0.1.0 CHANGELOG entry

Under `## 0.1.0`, add a short Keep a Changelog `### Added` list describing the initial release. Base it on `clients/cli/README.md` and `cli/`:

- the `pidgey` command sending one message through `pidgey-client`;
- the flags (`--to`, `--subject`, `--text`, `--html`, `--token-file`, `--url`, `--idempotency-key`, `--retry-ambiguous`, `--allow-insecure-http`, `--verbose`, `--help`, `--version`);
- the token and URL environment fallbacks;
- the body read from stdin;
- the documented exit codes;
- the dependency on `pidgey-client` `^0.1.0`;
- the Node.js `>=22.12.0` requirement.

Keep it to a few lines, and describe only what the code really does.

### Step 2 — Verify the release gate locally

From the repo root, run `out="$(mktemp -d)"; clients/scripts/release_pack.sh pidgey-cli-v0.1.0 "$out"`. It must pass and print `pidgey-client range in the packed CLI: ^0.1.0`. Then run `clients/scripts/release_publish.sh --dry-run pidgey-cli-v0.1.0 "$out/pidgey-cli-0.1.0.tgz"`; it only warns that the library floor is not on npm yet. Do not create or push a git tag.

## Files to Change

- `clients/cli/CHANGELOG.md`: add the `## 0.1.0` entry.

## CI Checks

- `clients/`: `clients/scripts/checks.sh` (CI job: `clients_checks`).
