#!/usr/bin/env bash
#
# check_openapi_breaking.sh — fail when the /v1 OpenAPI document has a
# breaking change (an oasdiff ERR-level finding) compared with its version at
# the merge-base with the base ref (default: origin/main).
#
# Usage:
#   scripts/check_openapi_breaking.sh <head document> [base ref]
#
# Environment:
#   OASDIFF_VERSION  tufin/oasdiff tag used by the docker fallback, when
#                    oasdiff is not on PATH (the Makefile exports it)
#
# Exit status: 0 when there is no ERR finding; non-zero on an ERR finding, on
# a missing merge-base or base document, or when oasdiff cannot be run. It
# never passes silently. WARN findings are printed but do not fail.

set -euo pipefail

BASE_DOCUMENT="source/openapi/v1.json"

if [ $# -lt 1 ] || [ $# -gt 2 ]; then
  echo "Usage: $0 <head document> [base ref=origin/main]" >&2
  exit 2
fi

head_document="$1"
base_ref="${2:-origin/main}"

if [ ! -f "$head_document" ]; then
  echo "Error: head document not found: $head_document" >&2
  exit 1
fi

if ! merge_base="$(git merge-base "$base_ref" HEAD 2>/dev/null)"; then
  echo "Error: no merge-base between '$base_ref' and HEAD (is '$base_ref' fetched?)" >&2
  exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

if ! git show "$merge_base:$BASE_DOCUMENT" > "$work/base.json" 2>/dev/null; then
  echo "Error: base document not found at $merge_base ($BASE_DOCUMENT)" >&2
  exit 1
fi
cp "$head_document" "$work/head.json"

echo "OpenAPI breaking-change check: base $base_ref (merge-base $merge_base:$BASE_DOCUMENT) vs head $head_document"

if command -v oasdiff >/dev/null 2>&1; then
  oasdiff breaking "$work/base.json" "$work/head.json" --fail-on ERR
elif command -v docker >/dev/null 2>&1; then
  docker run --rm -v "$work:/work:ro" "tufin/oasdiff:${OASDIFF_VERSION:?OASDIFF_VERSION must be set to use the docker fallback}" \
    breaking /work/base.json /work/head.json --fail-on ERR
else
  echo "Error: neither oasdiff nor docker is available" >&2
  exit 1
fi
