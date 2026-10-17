#!/usr/bin/env bash
set -euo pipefail
set -x

docker compose run --rm tests composer audit
docker compose run --rm frontend_dev yarn npm audit
