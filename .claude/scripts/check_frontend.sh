#!/usr/bin/env bash
set -euo pipefail
set -x

docker compose run --rm --no-deps frontend_dev yarn coverage
docker compose run --rm --no-deps frontend_dev yarn lint
