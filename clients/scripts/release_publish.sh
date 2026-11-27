#!/usr/bin/env bash
# Publishes the tarball of one client package, as packed and checked by
# release_pack.sh: the logic of the publish_* CI jobs. Installs nothing and
# never re-packs.
#
# Usage: release_publish.sh [--dry-run] <tarball>
#
# 1. If npm already serves the version, ends as a no-op success.
# 2. For pidgey-cli, waits until npm serves the pidgey-client floor.
# 3. Publishes the tarball, then waits until npm serves the new version.
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
  echo "Usage: $0 [--dry-run] <tarball>" >&2
  exit 1
}

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

# Evaluates a JavaScript expression against the package.json of the tarball.
manifest_field() {
  printf '%s' "$MANIFEST" \
    | node -p "const pkg = JSON.parse(require('fs').readFileSync(0, 'utf8')); $1"
}

DRY_RUN=false
if [ "${1:-}" = "--dry-run" ]; then
  DRY_RUN=true
  shift
fi

[ "$#" -eq 1 ] && [ -n "$1" ] || usage
case "$1" in
  -*) usage ;;
esac
[ -f "$1" ] || fail "tarball $1 not found"

# An absolute path, so npm can never read it as a git shorthand.
TARBALL="$(cd "$(dirname "$1")" && pwd)/$(basename "$1")"

MANIFEST="$(tar -xzOf "$TARBALL" package/package.json)"
NAME="$(manifest_field 'pkg.name')"
VERSION="$(manifest_field 'pkg.version')"

case "$NAME" in
  pidgey-client | pidgey-cli) ;;
  *) fail "unexpected package name \"${NAME}\" in ${TARBALL}" ;;
esac

VERSION_PATTERN='^[0-9]+\.[0-9]+\.[0-9]+$'
[[ "$VERSION" =~ $VERSION_PATTERN ]] || fail "unexpected version \"${VERSION}\" in ${TARBALL}"

# npm runs from an empty directory, so no project .npmrc or package.json is read.
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
cd "$WORK"

if is_published "${NAME}@${VERSION}"; then
  log "${NAME}@${VERSION} is already on npm: nothing to publish"
  exit 0
fi

if [ "$NAME" = "pidgey-cli" ]; then
  RANGE="$(manifest_field '(pkg.dependencies || {})["pidgey-client"] || ""')"
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
  npm publish "$TARBALL" --access public --ignore-scripts --registry "$REGISTRY" --dry-run
  log "Dry run of ${NAME}@${VERSION} finished: nothing was published"
  exit 0
fi

[ -n "${NPM_TOKEN:-}" ] || fail "NPM_TOKEN is not set"

# The file holds the variable name, not its value: npm expands it itself.
# shellcheck disable=SC2016
printf '%s\n' '//registry.npmjs.org/:_authToken=${NPM_TOKEN}' > "${WORK}/npmrc"

NPM_CONFIG_USERCONFIG="${WORK}/npmrc" \
  npm publish "$TARBALL" --access public --ignore-scripts --registry "$REGISTRY"

wait_for_version "${NAME}@${VERSION}" \
  || fail "npm does not serve ${NAME}@${VERSION} after ${WAIT_TIMEOUT} s; re-run the job to check again"

log "Published ${NAME}@${VERSION}"
