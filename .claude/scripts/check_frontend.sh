#!/usr/bin/env bash
set -euo pipefail
set -x

docker compose run --rm frontend_dev yarn lint
docker compose run --rm frontend_dev yarn test
