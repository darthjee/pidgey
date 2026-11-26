#!/usr/bin/env bash
# Pack smoke test: packs both packages, installs the tarballs together in an
# empty project and checks what a consumer would get. Nothing is fetched
# (offline). Writes only under a mktemp -d directory.
# Called by checks.sh, from any working directory.
set -euo pipefail

CLIENTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

fail() {
  echo "Smoke test failed: $1" >&2
  echo "  expected: $2" >&2
  echo "  found:    $3" >&2
  exit 1
}

assert_equals() {
  local what="$1" expected="$2" found="$3"

  if [ "$expected" != "$found" ]; then
    fail "$what" "$expected" "$found"
  fi
}

json_field() {
  node -p "const pkg = require(process.argv[1]); $2" "$1"
}

LIB_VERSION="$(json_field "${CLIENTS_DIR}/nodejs/package.json" 'pkg.version')"
CLI_VERSION="$(json_field "${CLIENTS_DIR}/cli/package.json" 'pkg.version')"

echo "== smoke test ==" >&2
mkdir -p "${WORK}/dist" "${WORK}/project"

LIB_PACK="$(cd "${CLIENTS_DIR}/nodejs" && npm pack --ignore-scripts --json --pack-destination "${WORK}/dist")"
LIB_TGZ="${WORK}/dist/$(printf '%s' "$LIB_PACK" | node -p 'JSON.parse(require("fs").readFileSync(0, "utf8"))[0].filename')"
CLI_TGZ="$("${CLIENTS_DIR}/scripts/pack_cli.sh" "${WORK}/dist")"

cd "${WORK}/project"
npm init -y > /dev/null
npm install --offline --ignore-scripts --no-audit --no-fund "$LIB_TGZ" "$CLI_TGZ" > /dev/null

assert_equals "pidgey-client range in the packed CLI" \
  "^${LIB_VERSION}" \
  "$(json_field "${WORK}/project/node_modules/pidgey-cli/package.json" 'pkg.dependencies["pidgey-client"]')"

ESM_RESULT="$(node --input-type=module -e "
  import { PidgeyClient, PidgeyError } from 'pidgey-client';
  console.log(typeof PidgeyClient + ' ' + typeof PidgeyError);
")"
assert_equals "ESM import of pidgey-client" "function function" "$ESM_RESULT"

CJS_RESULT="$(node -e "
  const client = require('pidgey-client');
  console.log(typeof client.PidgeyClient);
")"
assert_equals "CommonJS require of pidgey-client" "function" "$CJS_RESULT"

assert_equals "pidgey --version" \
  "pidgey-cli ${CLI_VERSION} (pidgey-client ${LIB_VERSION})" \
  "$(npx --no-install pidgey --version)"

HELP_STATUS=0
npx --no-install pidgey --help > /dev/null || HELP_STATUS=$?
assert_equals "pidgey --help exit status" "0" "$HELP_STATUS"

# The installed pidgey-client must ship usable type declarations: the consumer
# fixture imports only the package and is type-checked against it, offline.
cp "${CLIENTS_DIR}/nodejs/specs/types/consumer.ts" "${WORK}/project/consumer.ts"
cat > "${WORK}/project/tsconfig.json" <<'JSON'
{
  "compilerOptions": {
    "strict": true,
    "noEmit": true,
    "skipLibCheck": false,
    "module": "nodenext",
    "moduleResolution": "nodenext",
    "target": "es2022",
    "lib": ["es2022", "dom"],
    "types": []
  },
  "include": ["consumer.ts"]
}
JSON
node -e '
  const fs = require("fs");
  const file = process.argv[1];
  const pkg = JSON.parse(fs.readFileSync(file, "utf8"));
  pkg.type = "module";
  fs.writeFileSync(file, JSON.stringify(pkg, null, 2));
' "${WORK}/project/package.json"

if ! "${CLIENTS_DIR}/nodejs/node_modules/.bin/tsc" -p "${WORK}/project"; then
  echo "Smoke test failed: consumer.ts does not type-check against the installed pidgey-client" >&2
  exit 1
fi

echo "Smoke test passed" >&2
