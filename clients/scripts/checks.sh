#!/usr/bin/env bash
# Runs every check of the client packages under clients/.
# Called with no argument, from any working directory, by CI and by
# `make clients-checks`.
set -euo pipefail

CLIENTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PACKAGES="nodejs cli"

check_pack() {
  local pkg="$1"

  if [ ! -f pack-files.txt ]; then
    echo "clients/${pkg}/pack-files.txt is missing" >&2
    exit 1
  fi

  TMP_FILE="$(mktemp)"
  trap 'rm -f "$TMP_FILE"' EXIT
  npm pack --dry-run --json \
    | node -e '
        let input = "";
        process.stdin.on("data", (chunk) => { input += chunk; });
        process.stdin.on("end", () => {
          const [pack] = JSON.parse(input);
          pack.files.forEach((file) => console.log(file.path));
        });
      ' \
    | LC_ALL=C sort > "$TMP_FILE"

  if ! diff -u pack-files.txt "$TMP_FILE"; then
    echo "Pack file list of ${pkg} differs from pack-files.txt" >&2
    exit 1
  fi
}

check_committed_dependency() {
  local found

  found="$(node -p 'const pkg = require("./package.json"); (pkg.dependencies || {})["pidgey-client"] || ""')"
  if [ "$found" != "file:../nodejs" ]; then
    echo "clients/cli/package.json: dependencies[\"pidgey-client\"] must be exactly \"file:../nodejs\" (found \"${found}\")" >&2
    exit 1
  fi
}

check_package() {
  local pkg="$1"

  echo "== ${pkg} =="
  cd "${CLIENTS_DIR}/${pkg}"

  if [ "$pkg" = "cli" ]; then
    check_committed_dependency
    # The node_modules volume persists between runs: drop the old library
    # copy so Yarn copies the current clients/nodejs source again.
    rm -rf node_modules/pidgey-client
  fi

  yarn install --frozen-lockfile --check-files
  yarn lint
  yarn typecheck
  yarn coverage
  if [ "$pkg" = "nodejs" ]; then
    # Emit the .d.ts declarations (git-ignored dist/) before packing, so the
    # pack check and the CLI's file:../nodejs copy both hold them.
    yarn build:types
  fi
  check_pack "$pkg"
}

for pkg in $PACKAGES; do
  (check_package "$pkg")
done

"${CLIENTS_DIR}/scripts/smoke_test.sh"
