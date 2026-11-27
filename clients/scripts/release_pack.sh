#!/usr/bin/env bash
# Release gate and pack of one client package: the logic of the pack_* CI jobs,
# also runnable locally.
#
# Usage: release_pack.sh <tag> <out_dir>
#   <tag>      pidgey-client-vX.Y.Z (clients/nodejs) or pidgey-cli-vX.Y.Z
#              (clients/cli)
#   <out_dir>  directory that receives the tarball (created if missing)
#
# Fails unless the tag version equals the package.json version, CHANGELOG.md
# has a "## X.Y.Z" heading and the file list of the packed tarball equals
# pack-files.txt. Prints the absolute path of the tarball on stdout; everything
# else goes to stderr.
#
# The install runs no dependency lifecycle script. The packing is shared with
# release_publish.sh (release_lib.sh), which packs the checkout again and only
# publishes if the bytes are the same.
#
# Never enable shell tracing in a release script.
set -euo pipefail

CLIENTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"

fail() {
  echo "Release pack failed: $1" >&2
  exit 1
}

# shellcheck source-path=SCRIPTDIR source=release_lib.sh
source "${CLIENTS_DIR}/scripts/release_lib.sh"

if [ "$#" -ne 2 ] || [ -z "$1" ] || [ -z "$2" ]; then
  echo "Usage: $0 <tag> <out_dir>" >&2
  exit 1
fi

TAG="$1"

# Tag and version gate.
read_release_tag "$TAG"

# CHANGELOG gate: a level-2 heading starting with the bare version.
CHANGELOG="${PACKAGE_DIR}/CHANGELOG.md"
[ -f "$CHANGELOG" ] || fail "${CHANGELOG} is missing"
grep -Eq "^## ${VERSION//./\\.}( |\$)" "$CHANGELOG" \
  || fail "${CHANGELOG} has no \"## ${VERSION}\" entry"

(cd "$PACKAGE_DIR" && yarn install --frozen-lockfile --ignore-scripts >&2)

if [ "$KIND" = "client" ]; then
  # Emit the .d.ts declarations (git-ignored dist/) so the tarball holds them.
  (cd "$PACKAGE_DIR" && yarn build:types >&2)
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

pack_release_package "${PACKAGE_DIR}/dist" "$2"

check_tarball_files "$TARBALL" "${PACKAGE_DIR}/pack-files.txt"

if [ "$KIND" = "cli" ]; then
  FLOOR="$(tar -xzOf "$TARBALL" package/package.json \
    | node -p 'JSON.parse(require("fs").readFileSync(0, "utf8")).dependencies["pidgey-client"]')"
  echo "pidgey-client range in the packed CLI: ${FLOOR}" >&2
fi

echo "Packed ${TAG}" >&2
echo "$TARBALL"
