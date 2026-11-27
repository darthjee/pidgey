# Release publish script with dry-run mode

Add `clients/scripts/release_publish.sh [--dry-run] <tarball>`, the logic of the `publish_*` jobs. `set -euo pipefail`, never `set -x`, installs nothing.

1. Read `name` and `version` from the tarball's `package/package.json` (`tar -xzOf <tarball> package/package.json | node -p ...`). Never re-pack.
2. **Already published → no-op**: `npm view <name>@<version> version`. Non-empty output means the version is already on npm: print a message and `exit 0`. Empty output, or an E404 for a package that is not on npm at all, means "not published". Any other npm error (network, 5xx) fails the job; do not treat it as "not published".
3. **CLI floor wait** (only when `name` is `pidgey-cli`): take the floor from `dependencies["pidgey-client"]` in the tarball's `package.json` (strip the leading `^`). Poll `npm view pidgey-client@<floor> version` every 10 s for up to 5 min, and abort with a clear message if it never shows up. With `--dry-run`, check once and print a warning instead of waiting or failing: before #254 the `0.1.0` floor is not on npm yet.
4. **Auth**: only when not `--dry-run`, require `NPM_TOKEN` to be set (fail without printing it), and write a temporary userconfig whose content is the literal line `//registry.npmjs.org/:_authToken=${NPM_TOKEN}` (npm expands the variable itself, so the token never lands in a file or a log). Point npm at it with `NPM_CONFIG_USERCONFIG` and remove it on exit with `trap`.
5. **Publish**: `npm publish <tarball> --access public --ignore-scripts`, plus `--provenance` if step 04 finds it supported (behind a single variable/flag in the script). With `--dry-run`, add `--dry-run` and never `--provenance`.
6. **Wait for npm** (skipped with `--dry-run`): poll `npm view <name>@<version> version` every 10 s for up to 5 min, and fail if it never answers.

## Files to Change
- `clients/scripts/release_publish.sh` — new.
