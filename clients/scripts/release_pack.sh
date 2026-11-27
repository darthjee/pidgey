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
# Never enable shell tracing in a release script.
set -euo pipefail

CLIENTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"

fail() {
  echo "Release pack failed: $1" >&2
  exit 1
}

# Compares the files of the tarball, without the package/ prefix, with the
# expected file list of the package.
check_tarball_files() {
  local tarball="$1" expected="$2" entries

  [ -f "$expected" ] || fail "${expected} is missing"

  entries="$(tar -tzf "$tarball")"
  if grep -qv '^package/' <<< "$entries"; then
    fail "${tarball} holds entries outside package/"
  fi

  # Every entry starts with package/ (checked above): drop that first segment.
  if ! cut -d/ -f2- <<< "$entries" | LC_ALL=C sort | diff -u "$expected" - >&2; then
    fail "the file list of ${tarball} differs from ${expected}"
  fi
}

if [ "$#" -ne 2 ] || [ -z "$1" ] || [ -z "$2" ]; then
  echo "Usage: $0 <tag> <out_dir>" >&2
  exit 1
fi

TAG="$1"
TAG_PATTERN='^pidgey-(client|cli)-v([0-9]+\.[0-9]+\.[0-9]+)$'

[[ "$TAG" =~ $TAG_PATTERN ]] \
  || fail "tag \"${TAG}\" is neither pidgey-client-vX.Y.Z nor pidgey-cli-vX.Y.Z"

KIND="${BASH_REMATCH[1]}"
VERSION="${BASH_REMATCH[2]}"

case "$KIND" in
  client) PACKAGE_DIR="${CLIENTS_DIR}/nodejs" ;;
  cli) PACKAGE_DIR="${CLIENTS_DIR}/cli" ;;
esac

# Version gate.
PACKAGE_VERSION="$(node -p 'require(process.argv[1]).version' "${PACKAGE_DIR}/package.json")"
[ "$VERSION" = "$PACKAGE_VERSION" ] \
  || fail "tag version ${VERSION} differs from the package.json version ${PACKAGE_VERSION} of ${PACKAGE_DIR}"

# CHANGELOG gate: a level-2 heading starting with the bare version.
CHANGELOG="${PACKAGE_DIR}/CHANGELOG.md"
[ -f "$CHANGELOG" ] || fail "${CHANGELOG} is missing"
grep -Eq "^## ${VERSION//./\\.}( |\$)" "$CHANGELOG" \
  || fail "${CHANGELOG} has no \"## ${VERSION}\" entry"

(cd "$PACKAGE_DIR" && yarn install --frozen-lockfile >&2)

mkdir -p "$2"
OUT_DIR="$(cd "$2" && pwd)"

if [ "$KIND" = "client" ]; then
  # Emit the .d.ts declarations (git-ignored dist/) so the tarball holds them.
  (cd "$PACKAGE_DIR" && yarn build:types >&2)
  PACK_JSON="$(cd "$PACKAGE_DIR" && npm pack --ignore-scripts --json --pack-destination "$OUT_DIR")"
  TARBALL="${OUT_DIR}/$(printf '%s' "$PACK_JSON" \
    | node -p 'JSON.parse(require("fs").readFileSync(0, "utf8"))[0].filename')"
else
  # The staging copy rewrites the library floor to ^<library version>.
  TARBALL="$("${CLIENTS_DIR}/scripts/pack_cli.sh" "$OUT_DIR")"
fi

check_tarball_files "$TARBALL" "${PACKAGE_DIR}/pack-files.txt"

if [ "$KIND" = "cli" ]; then
  FLOOR="$(tar -xzOf "$TARBALL" package/package.json \
    | node -p 'JSON.parse(require("fs").readFileSync(0, "utf8")).dependencies["pidgey-client"]')"
  echo "pidgey-client range in the packed CLI: ${FLOOR}" >&2
fi

echo "Packed ${TAG}" >&2
echo "$TARBALL"
