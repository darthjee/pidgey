# Compose, .env, volumes and .gitignore
Create `docker-compose.yml` with YAML anchors, the committed dev `.env`, the `docker_volumes/`
placeholders and the ignore rules.

`docker-compose.yml`:
- A `x-base: &base` anchor: image `darthjee/dev_pidgey`, `env_file: .env`, volumes
  `./source:/home/app/app` and `./docker_volumes/vendor:/home/app/app/vendor`.
- `base_build`: `<<: *base`, plus a build with context `source`, dockerfile
  `../dockerfiles/dev_pidgey/Dockerfile` and args `BASE_VERSION` (the Makefile's value) and
  `BASE_SUFFIX: ${BASE_SUFFIX:-}`. Command: a no-op such as `true`.
- `app`: `<<: *base`, ports `8080:80`, `PHP_ENV=development`, depends on `base_build` and
  `mysql`, command `apache2-foreground`.
- `tests`: `<<: *base`, `PHP_ENV=test`, depends on `base_build`, `mysql` and `mailpit`.
- `mysql`: `mysql:9`, `env_file: .env`, `./docker_volumes/mysql_data:/var/lib/mysql`, `3306:3306`.
- `mailpit`: `axllent/mailpit`, ports `8025:8025` and `1025:1025`.
- `phpmyadmin`: `phpmyadmin`, `PMA_HOST=mysql`, `8040:80`, depends on `mysql`.

`.env` (committed; development-only values, never real secrets): exactly the keys in the plan's
shared contracts, with obvious dev placeholders (for example `pidgey`/`pidgey`, root `root`).
Add a header comment saying the values are development-only.

`docker_volumes/mysql_data/.gitkeep` and `docker_volumes/vendor/.gitkeep`.

`.gitignore`, appended to the existing entries:
- `docker_volumes/*/*`
- `!docker_volumes/*/.gitkeep`
- `source/vendor/`
- `source/coverage`
- `source/.phpunit.result.cache`
- `source/complexity.html`

## Files to Change
- `docker-compose.yml` — new.
- `.env` — new; committed dev-only values.
- `docker_volumes/mysql_data/.gitkeep`, `docker_volumes/vendor/.gitkeep` — new.
- `.gitignore` — add the ignore rules.
