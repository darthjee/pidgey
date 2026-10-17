#!/usr/bin/env bash
set -euo pipefail
set -x

docker compose run --rm tests composer lint
docker compose run --rm tests composer tests
