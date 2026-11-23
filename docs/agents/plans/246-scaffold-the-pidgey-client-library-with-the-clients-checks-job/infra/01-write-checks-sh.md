# Write checks.sh

Write `clients/scripts/checks.sh`, the one script that CI and `make` both call (`docs/agents/specs/node-client/testing.md`, CI and `make tests`).

- `#!/usr/bin/env bash`, `set -euo pipefail`. Resolve `CLIENTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"`.
- `PACKAGES="nodejs"`, plus a `check_package <pkg>` function. In `clients/<pkg>/`, it:
  1. prints a `== <pkg> ==` header;
  2. runs `yarn install --frozen-lockfile`, then `yarn lint`, `yarn typecheck` and `yarn coverage`;
  3. runs the pack check: `npm pack --dry-run --json`, extracts `files[].path` with a `node -e` one-liner, sorts it with `LC_ALL=C sort`, and writes it to a `mktemp` file. It then runs `diff -u pack-files.txt <tmp>`. On a difference it prints `Pack file list of <pkg> differs from pack-files.txt` and exits `1`. A missing `pack-files.txt` is also a failure, with a clear message.
- Remove the temp file with a `trap`. Make the script executable (`git update-index --chmod=+x` if needed).
- No network beyond `yarn install`, and no write outside the package folder.

## Files to Change
- `clients/scripts/checks.sh`: new, executable.
