# Fix the check scripts
`frontend_dev` depends on `app`, so every frontend command uses `--no-deps` (as `make tests` and `make fe-install` already do). The image ships Yarn 1.22.22, where `yarn npm audit` does not exist; the Yarn 1 command is `yarn audit`.

- `.claude/scripts/check_frontend.sh`: keep the `set -euo pipefail` / `set -x` header and replace the two commands with:
  ```bash
  docker compose run --rm --no-deps frontend_dev yarn coverage
  docker compose run --rm --no-deps frontend_dev yarn lint
  ```
- `.claude/scripts/check_security.sh`: replace `docker compose run --rm frontend_dev yarn npm audit` with `docker compose run --rm --no-deps frontend_dev yarn audit`. Leave the `composer audit` line as it is.

Run both scripts. `check_frontend.sh` must pass. For `check_security.sh`, `yarn audit` must actually run; report any advisories it finds.

## Files to Change
- `.claude/scripts/check_frontend.sh` — coverage + lint, `--no-deps`.
- `.claude/scripts/check_security.sh` — `yarn audit` with `--no-deps`.
