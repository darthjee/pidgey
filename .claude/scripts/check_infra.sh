#!/usr/bin/env bash
set -euo pipefail
set -x

docker compose config -q
make build
