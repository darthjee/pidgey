#!/usr/bin/env bash
#
# bump-version.sh — bump pidgey's release version across the places that
# must stay in sync:
#   - README.md              (Current Version / Next Release lines)
#   - frontend/package.json  ("version" field)
#
# Usage:
#   scripts/bump-version.sh [X.Y.Z]
#
# With no argument, the new version defaults to README's current
# "Next Release" value. Plain semver only (X.Y.Z, no "v" prefix).
#
# This script only edits files — it does not git add/commit/tag anything.

set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
README="$ROOT/README.md"
PACKAGE="$ROOT/frontend/package.json"
REPO_SLUG="darthjee/pidgey"

# Exit with an error unless $1 is in X.Y.Z format
validate_semver() {
  if ! echo "$1" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$'; then
    echo "Error: version must be in X.Y.Z format" >&2
    exit 1
  fi
}

# Extract current Next Release version from README
current_next=$(grep -oE '\*\*Next Release:\*\* \[[0-9]+\.[0-9]+\.[0-9]+' "$README" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')

new_version="${1:-$current_next}"
validate_semver "$new_version"

# Compute next release (increment patch)
IFS='.' read -r major minor patch <<< "$new_version"
next_release="${major}.${minor}.$((patch + 1))"

echo "New version:  $new_version"
echo "Next release: $next_release"

# Update README Current Version line
sed -i '' \
  "s|\*\*Current Version:\*\* \[[0-9.]*\](https://github.com/${REPO_SLUG}/releases/tag/[0-9.]*)|\*\*Current Version:\*\* [${new_version}](https://github.com/${REPO_SLUG}/releases/tag/${new_version})|" \
  "$README"

# Update README Next Release line
sed -i '' \
  "s|\*\*Next Release:\*\* \[[0-9.]*\](https://github.com/${REPO_SLUG}/compare/[0-9.]*\.\.\.main)|\*\*Next Release:\*\* [${next_release}](https://github.com/${REPO_SLUG}/compare/${new_version}...main)|" \
  "$README"

# Update frontend/package.json version (top-level field only)
sed -i '' \
  "s|^  \"version\": \"[0-9.]*\"|  \"version\": \"${new_version}\"|" \
  "$PACKAGE"

echo "Done."
