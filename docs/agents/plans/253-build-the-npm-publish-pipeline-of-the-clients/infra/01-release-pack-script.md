# Release pack script

Add `clients/scripts/release_pack.sh <tag> <out_dir>`, the logic of the `pack_*` jobs, runnable locally too. `set -euo pipefail`, no `set -x`; diagnostics to stderr; prints the absolute tarball path on stdout (same convention as `pack_cli.sh`).

1. Parse `<tag>` against `^pidgey-(client|cli)-v([0-9]+\.[0-9]+\.[0-9]+)$`. Map `client` → `clients/nodejs` and `cli` → `clients/cli`. Fail on any other tag.
2. **Version gate**: the tag version must equal `package.json` `version` of that package.
3. **CHANGELOG gate**: `CHANGELOG.md` exists and has a line matching `^## <version>( |$)` (escape the dots).
4. **Frozen install**: `yarn install --frozen-lockfile` in the package dir. For the library, also `yarn build:types`, so `dist/` is in the tarball (same as `checks.sh`).
5. **Pack**:
   - library: `npm pack --ignore-scripts --json --pack-destination <out_dir>` in `clients/nodejs`;
   - CLI: `clients/scripts/pack_cli.sh <out_dir>` (the staging copy rewrites the floor to `^<library version>`).
6. **Pack check on the actual tarball**: list `tar -tzf <tarball>`, strip the `package/` prefix, `LC_ALL=C sort`, and `diff -u` against the package's `pack-files.txt`; fail on any difference. Factor the comparison into a small function, or share it with `checks.sh` if that stays simple.
7. For the CLI, also print (stderr) the floor read back from the tarball's `package.json` (`dependencies["pidgey-client"]`), so the publish step can use it. Simplest: the publish script reads it from the tarball itself (see step 02).

## Files to Change
- `clients/scripts/release_pack.sh` — new.
- `clients/scripts/checks.sh` — only if the pack-list comparison is shared.
