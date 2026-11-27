# Nodejs Plan: Release pidgey-client and pidgey-cli 0.1.0

Main plan: [plan.md](plan.md)

## Shared contracts

- Add a bare `## 0.1.0` heading below the empty `## Unreleased` in `clients/nodejs/CHANGELOG.md`, matching the gate regex `^## 0\.1\.0( |$)` of `clients/scripts/release_pack.sh`.
- `clients/nodejs/package.json` stays at `0.1.0`; do not change it.

## Implementation Steps

### Step 1 — Add the 0.1.0 CHANGELOG entry

Under `## 0.1.0`, add a short Keep a Changelog `### Added` list describing the initial release. Base it on `clients/nodejs/README.md` and `lib/`:

- `PidgeyClient` with `send` for `POST /v1/messages`;
- `PidgeyError`, with the server and client-local codes and `possiblySent`;
- the retry policy (`maxRetries`, `retryAmbiguous`, `retryDelayMs`) and the generated `Idempotency-Key`;
- `timeoutMs` and `AbortSignal` support;
- the cleartext `http://` guard with its loopback exemption;
- TypeScript declarations generated from the OpenAPI document;
- the `VERSION` export;
- the Node.js `>=22.12.0` requirement.

Keep it to a few lines, and describe only what the code really does.

### Step 2 — Verify the release gate locally

From the repo root, run `out="$(mktemp -d)"; clients/scripts/release_pack.sh pidgey-client-v0.1.0 "$out"`, then `clients/scripts/release_publish.sh --dry-run pidgey-client-v0.1.0 "$out/pidgey-client-0.1.0.tgz"`. Both must pass. Do not create or push a git tag.

## Files to Change

- `clients/nodejs/CHANGELOG.md`: add the `## 0.1.0` entry.

## CI Checks

- `clients/`: `clients/scripts/checks.sh` (CI job: `clients_checks`).

## Notes

- `CHANGELOG.md` is not in the tarball, so `pack-files.txt` does not change.
