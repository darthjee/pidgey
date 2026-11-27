#!/usr/bin/env bash
# Publishes one client package: the logic of the publish_* CI jobs. Installs
# nothing and runs no lifecycle script.
#
# Usage: release_publish.sh [--dry-run] <tag> <tarball>
#   <tag>      pidgey-client-vX.Y.Z (clients/nodejs) or pidgey-cli-vX.Y.Z
#              (clients/cli)
#   <tarball>  the tarball packed by release_pack.sh for that tag
#
# <tarball> comes from a job that runs third-party code, so it is not trusted:
# npm is never given it and nothing is decided from its content.
#
# 1. The package name, directory and version come from the tag; the package.json
#    of the checkout must have that version.
# 2. A reference tarball is packed from the checkout, the way release_pack.sh
#    packs. Only the library's dist/ (git-ignored build output) is taken from a
#    copy of <tarball>, which must hold regular files only.
# 3. The file list of the reference must equal pack-files.txt, and the reference
#    must be byte-identical to <tarball>.
# 4. If npm already serves the version, ends as a no-op success.
# 5. For pidgey-cli, waits until npm serves the pidgey-client floor.
# 6. Publishes the reference, then waits until npm serves the new version.
#
# NPM_TOKEN is required to publish, and is not read with --dry-run. A dry run
# runs `npm publish --dry-run`, checks the floor once (a missing floor is only a
# warning) and does not wait for npm.
#
# No --provenance: npm generates provenance only on GitHub Actions and GitLab
# CI/CD, not on CircleCI.
#
# Never enable shell tracing in a release script, and never print the token.
set -euo pipefail

CLIENTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"
REGISTRY="https://registry.npmjs.org/"
WAIT_INTERVAL=10
WAIT_TIMEOUT=300

log() {
  echo "$1" >&2
}

fail() {
  echo "Release publish failed: $1" >&2
  exit 1
}

usage() {
  echo "Usage: $0 [--dry-run] <tag> <tarball>" >&2
  exit 1
}

# shellcheck source-path=SCRIPTDIR source=release_lib.sh
source "${CLIENTS_DIR}/scripts/release_lib.sh"

# Succeeds when npm serves <name>@<version>, and returns 1 when it does not: an
# empty answer or an E404, depending on the npm version and on whether the
# package exists. Any other npm error ends the script.
is_published() {
  local spec="$1" found status=0

  found="$(npm view "$spec" version --registry "$REGISTRY" --color=false 2> "${WORK}/view.err")" \
    || status=$?

  if [ "$status" -eq 0 ]; then
    [ -n "$found" ]
    return
  fi

  if grep -Eq '^npm (error|ERR!) code E404$' "${WORK}/view.err"; then
    return 1
  fi

  cat "${WORK}/view.err" >&2
  fail "npm view ${spec} failed with exit status ${status}"
}

# Polls until npm serves <name>@<version>; returns 1 after WAIT_TIMEOUT seconds.
wait_for_version() {
  local spec="$1" deadline=$((SECONDS + WAIT_TIMEOUT))

  until is_published "$spec"; do
    [ "$SECONDS" -lt "$deadline" ] || return 1
    log "${spec} is not on npm yet, checking again in ${WAIT_INTERVAL} s"
    sleep "$WAIT_INTERVAL"
  done
}

# extract_dist <tarball> <dir>
#
# Extracts package/dist, and nothing else, from a tarball that is not trusted.
# npm packs regular files only, so any other kind of entry (such as a link) is
# rejected before anything is extracted.
extract_dist() {
  local tarball="$1" dir="$2" listing

  listing="$(tar -tvzf "$tarball")" || fail "cannot list the tarball"
  if grep -qv '^-' <<< "$listing"; then
    fail "the tarball holds entries that are not regular files"
  fi

  mkdir "$dir"
  tar -xzf "$tarball" -C "$dir" package/dist || fail "cannot extract package/dist from the tarball"
}

DRY_RUN=false
if [ "${1:-}" = "--dry-run" ]; then
  DRY_RUN=true
  shift
fi

[ "$#" -eq 2 ] && [ -n "$1" ] && [ -n "$2" ] || usage

# Tag and version gate: sets what is published, whatever the tarball says.
read_release_tag "$1"

[ -f "$2" ] || fail "tarball $2 not found"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# A private copy, so the bytes extracted are the bytes compared.
CANDIDATE="${WORK}/candidate.tgz"
cp -- "$2" "$CANDIDATE"

DIST_DIR=""
if [ "$KIND" = "client" ]; then
  extract_dist "$CANDIDATE" "${WORK}/candidate"
  DIST_DIR="${WORK}/candidate/package/dist"
fi

pack_release_package "$DIST_DIR" "${WORK}/reference"

# The only tarball npm is given: an absolute path under the work directory, named
# <name>-<version>.tgz (pack_release_package), which npm reads as a local file.
REFERENCE="$TARBALL"

# Rejects any file of dist/ that the expected list does not name.
check_tarball_files "$REFERENCE" "${PACKAGE_DIR}/pack-files.txt"

cmp -s "$REFERENCE" "$CANDIDATE" \
  || fail "tarball $2 differs from the one packed from this checkout: nothing is published"
log "Tarball $2 is byte-identical to the one packed from this checkout"

# npm runs from an empty directory, so no project .npmrc or package.json is read.
mkdir "${WORK}/run"
cd "${WORK}/run"

if is_published "${PACKAGE_NAME}@${VERSION}"; then
  log "${PACKAGE_NAME}@${VERSION} is already on npm: nothing to publish"
  exit 0
fi

if [ "$KIND" = "cli" ]; then
  RANGE="$(tar -xzOf "$REFERENCE" package/package.json \
    | node -p 'const pkg = JSON.parse(require("fs").readFileSync(0, "utf8")); (pkg.dependencies || {})["pidgey-client"] || ""')"
  RANGE_PATTERN='^\^([0-9]+\.[0-9]+\.[0-9]+)$'
  [[ "$RANGE" =~ $RANGE_PATTERN ]] \
    || fail "dependencies[\"pidgey-client\"] must be ^X.Y.Z (found \"${RANGE}\")"
  FLOOR="pidgey-client@${BASH_REMATCH[1]}"

  if [ "$DRY_RUN" = true ]; then
    if is_published "$FLOOR"; then
      log "${FLOOR} is on npm"
    else
      log "Warning: ${FLOOR} is not on npm; a real publish would wait for it, then fail"
    fi
  else
    wait_for_version "$FLOOR" \
      || fail "${FLOOR} did not show up on npm within ${WAIT_TIMEOUT} s: publish it first"
    log "${FLOOR} is on npm"
  fi
fi

if [ "$DRY_RUN" = true ]; then
  npm publish "$REFERENCE" --access public --ignore-scripts --registry "$REGISTRY" --dry-run
  log "Dry run of ${PACKAGE_NAME}@${VERSION} finished: nothing was published"
  exit 0
fi

[ -n "${NPM_TOKEN:-}" ] || fail "NPM_TOKEN is not set"

# The file holds the variable name, not its value: npm expands it itself.
# shellcheck disable=SC2016
printf '%s\n' '//registry.npmjs.org/:_authToken=${NPM_TOKEN}' > "${WORK}/npmrc"

NPM_CONFIG_USERCONFIG="${WORK}/npmrc" \
  npm publish "$REFERENCE" --access public --ignore-scripts --registry "$REGISTRY"

wait_for_version "${PACKAGE_NAME}@${VERSION}" \
  || fail "npm does not serve ${PACKAGE_NAME}@${VERSION} after ${WAIT_TIMEOUT} s; re-run the job to check again"

log "Published ${PACKAGE_NAME}@${VERSION}"
