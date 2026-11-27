#!/usr/bin/env bash
# End-to-end check of the client packages against a running Pidgey.
# Called with no argument, from any working directory, inside the `clients`
# container, by CI and by `make clients-integration` (which starts the
# integration_app, mysql and mailpit-plain services first).
set -euo pipefail

CLIENTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PACKAGES="nodejs cli"
WAIT_SECONDS=60

export PIDGEY_INTEGRATION_URL="${PIDGEY_INTEGRATION_URL:-http://integration_app}"
export PIDGEY_INTEGRATION_MAILPIT_URL="${PIDGEY_INTEGRATION_MAILPIT_URL:-http://mailpit-plain:9025}"

# Polls a URL until it answers 200, for at most WAIT_SECONDS.
wait_for() {
  local name="$1"
  local url="$2"
  local attempt

  for attempt in $(seq 1 "$WAIT_SECONDS"); do
    if node -e '
          fetch(process.argv[1])
            .then((response) => process.exit(response.status === 200 ? 0 : 1))
            .catch(() => process.exit(1));
        ' "$url"; then
      return 0
    fi
    sleep 1
  done

  echo "${name} did not answer 200 at ${url} within ${WAIT_SECONDS}s" >&2
  exit 1
}

wait_for "Pidgey" "${PIDGEY_INTEGRATION_URL}/health.json"
wait_for "Mailpit" "${PIDGEY_INTEGRATION_MAILPIT_URL}/api/v1/info"

# The seed writes the tokens to a private file, never to the job output.
SEED_FILE="$(mktemp)"
trap 'rm -f "$SEED_FILE"' EXIT
chmod 600 "$SEED_FILE"

node "${CLIENTS_DIR}/scripts/integration_seed.mjs" "$SEED_FILE"

set -a
# shellcheck disable=SC1090
. "$SEED_FILE"
set +a

export PIDGEY_INTEGRATION=required

run_package() {
  local pkg="$1"

  echo "== ${pkg} integration =="
  cd "${CLIENTS_DIR}/${pkg}"

  if [ "$pkg" = "cli" ]; then
    # Drop the old library copy so Yarn copies the current clients/nodejs
    # source again (same reason as in checks.sh).
    rm -rf node_modules/pidgey-client
  fi

  yarn install --frozen-lockfile --check-files
  yarn test:integration
}

for pkg in $PACKAGES; do
  (run_package "$pkg")
done
