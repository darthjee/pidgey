# shellcheck shell=bash
# Shared by release_pack.sh and release_publish.sh, so both read the release tag,
# pack and check the file list in exactly the same way: the publish job packs the
# checkout again and requires the bytes of the pack job. Sourced, never run.
#
# The sourcing script runs with `set -euo pipefail`, sets CLIENTS_DIR, defines
# fail() and, before packing, sets WORK to a mktemp -d directory that it removes
# on exit.
#
# The functions set variables instead of printing: never call them in a command
# substitution, where bash would not stop them on the first error.

RELEASE_TAG_PATTERN='^pidgey-(client|cli)-v([0-9]+\.[0-9]+\.[0-9]+)$'

# Sets KIND, VERSION, PACKAGE_NAME and PACKAGE_DIR from the tag alone, then fails
# unless the package.json of the checkout has that version.
read_release_tag() {
  local tag="$1" found

  [[ "$tag" =~ $RELEASE_TAG_PATTERN ]] \
    || fail "tag \"${tag}\" is neither pidgey-client-vX.Y.Z nor pidgey-cli-vX.Y.Z"

  KIND="${BASH_REMATCH[1]}"
  VERSION="${BASH_REMATCH[2]}"
  PACKAGE_NAME="pidgey-${KIND}"

  case "$KIND" in
    client) PACKAGE_DIR="${CLIENTS_DIR}/nodejs" ;;
    cli) PACKAGE_DIR="${CLIENTS_DIR}/cli" ;;
  esac

  found="$(node -p 'require(process.argv[1]).version' "${PACKAGE_DIR}/package.json")"
  [ "$VERSION" = "$found" ] \
    || fail "tag version ${VERSION} differs from the package.json version ${found} of ${PACKAGE_DIR}"
}

# pack_release_package <dist_dir> <out_dir>
#
# Packs the package of the tag from the checkout into <out_dir> and sets TARBALL
# to the absolute path <out_dir>/<name>-<version>.tgz. <dist_dir> is the dist/ of
# the library, and is not used for the CLI. Both packages are packed from a
# staging copy with normalized file modes, so the bytes depend only on the
# checkout and <dist_dir>, not on the umask of the job.
pack_release_package() {
  local dist_dir="$1" out_dir stage="${WORK}/stage" pack_json

  mkdir -p "$2"
  out_dir="$(cd "$2" && pwd)"

  if [ "$KIND" = "cli" ]; then
    # The staging copy rewrites the library floor to ^<library version>.
    TARBALL="$("${CLIENTS_DIR}/scripts/pack_cli.sh" "$out_dir")"
  else
    mkdir "$stage"
    tar -C "$PACKAGE_DIR" --exclude=./node_modules --exclude=./coverage --exclude=./dist -cf - . \
      | tar -C "$stage" -xf -
    cp -R "$dist_dir" "${stage}/dist"
    # dist/ holds type declarations only: never executable.
    find "${stage}/dist" -type f -exec chmod a-x {} +
    chmod -R u=rwX,go=rX "$stage"
    pack_json="$(cd "$stage" && npm pack --ignore-scripts --json --pack-destination "$out_dir")"
    TARBALL="${out_dir}/$(printf '%s' "$pack_json" \
      | node -p 'JSON.parse(require("fs").readFileSync(0, "utf8"))[0].filename')"
  fi

  [ "$TARBALL" = "${out_dir}/${PACKAGE_NAME}-${VERSION}.tgz" ] \
    || fail "npm packed ${TARBALL}, expected ${PACKAGE_NAME}-${VERSION}.tgz"
}

# Compares the files of a tarball packed here, without the package/ prefix, with
# the expected file list of the package. Not enough to inspect a tarball that is
# not trusted: tar and npm do not read every archive in the same way.
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
