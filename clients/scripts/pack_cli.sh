#!/usr/bin/env bash
# Packs clients/cli/ from a staging copy, so the committed tree is never
# modified. In the copy, dependencies["pidgey-client"] is rewritten from the
# local `file:../nodejs` to `^<version of clients/nodejs/package.json>`.
#
# Usage: pack_cli.sh <out_dir>
# Prints the absolute path of the tarball on stdout; everything else goes to
# stderr. Writes only under a mktemp -d directory and <out_dir>.
set -euo pipefail

if [ "$#" -ne 1 ] || [ -z "$1" ]; then
  echo "Usage: $0 <out_dir>" >&2
  exit 1
fi

CLIENTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"
mkdir -p "$1"
OUT_DIR="$(cd "$1" && pwd)"

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

tar -C "${CLIENTS_DIR}/cli" --exclude=./node_modules --exclude=./coverage -cf - . \
  | tar -C "$STAGE" -xf -

FLOOR="$(node -p 'require(process.argv[1]).version' "${CLIENTS_DIR}/nodejs/package.json")"

node -e '
  const fs = require("fs");
  const [file, floor] = process.argv.slice(1);
  const pkg = JSON.parse(fs.readFileSync(file, "utf8"));
  if (!pkg.dependencies || !pkg.dependencies["pidgey-client"]) {
    console.error("dependencies[\"pidgey-client\"] is missing in " + file);
    process.exit(1);
  }
  pkg.dependencies["pidgey-client"] = "^" + floor;
  fs.writeFileSync(file, JSON.stringify(pkg, null, 2) + "\n");
' "${STAGE}/package.json" "$FLOOR"

# Normalized modes: the packed bytes do not depend on the umask of the checkout
# or of this shell (release_publish.sh compares them).
chmod -R u=rwX,go=rX "$STAGE"

PACK_JSON="$(cd "$STAGE" && npm pack --ignore-scripts --json --pack-destination "$OUT_DIR")"

TARBALL="$(printf '%s' "$PACK_JSON" | node -e '
  let input = "";
  process.stdin.on("data", (chunk) => { input += chunk; });
  process.stdin.on("end", () => { console.log(JSON.parse(input)[0].filename); });
')"

echo "${OUT_DIR}/${TARBALL}"
