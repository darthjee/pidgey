# Nodejs Plan: Build the npm publish pipeline of the clients

Main plan: [plan.md](plan.md)

## Shared contracts

- `clients/nodejs/CHANGELOG.md` starts with `# Changelog`, then a single `## Unreleased` section (empty, or a one-line note). No version entry: the `0.1.0` entry belongs to the release PR of #254.
- Release entries later use `## <version>` (optionally ` - YYYY-MM-DD`). The infra pack gate matches `^## <version>( |$)`.
- Do not add `CHANGELOG.md` to `files` in `package.json`; `pack-files.txt` must not change.

## Implementation Steps

### Step 1 — Add the CHANGELOG skeleton
Create `clients/nodejs/CHANGELOG.md` with the shape above, plus a short line describing the format (Keep a Changelog style, versions per [packaging.md](../../specs/node-client/packaging.md#versioning)).

## Files to Change
- `clients/nodejs/CHANGELOG.md` — new skeleton.

## CI Checks
- `clients/`: `clients/scripts/checks.sh` (CI job: `clients_checks`). The pack check must still match `pack-files.txt`.

## Notes
- npm 7+ no longer auto-includes `CHANGELOG*` in the tarball; the pack check confirms it.
