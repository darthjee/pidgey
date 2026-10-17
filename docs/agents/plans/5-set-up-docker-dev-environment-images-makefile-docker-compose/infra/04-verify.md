# End-to-end verification
Run the issue's acceptance checks from a clean state, after backend has committed
`composer.lock`.

1. `make build-base && make build` (add `BASE_SUFFIX=-arm64` on Apple Silicon) succeeds.
2. `docker compose run --rm tests composer install` succeeds.
3. `docker compose up -d app mysql mailpit phpmyadmin`, then
   `curl -i http://localhost:8080/` returns `200` with `Content-Type: application/json` and
   `{"status":"ok"}`.
4. The MySQL port 3306 accepts connections with the `.env` user against `pidgey_development`.
   The Mailpit UI on 8025 and phpMyAdmin on 8040 respond.
5. `docker compose down`. Running `git status` shows nothing generated under
   `docker_volumes/` or `source/vendor/`.

Fix any problems in the files from steps 01–03. Report the results in the PR description.

## Files to Change
- None, beyond fixes to the files from steps 01–03.
