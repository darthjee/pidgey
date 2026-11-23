# Add the compose service and make targets

- `docker-compose.yml`: add the service `clients`:
  - `image: darthjee/node:0.2.1`, `working_dir: /home/node/app`;
  - volumes `./clients:/home/node/app/clients` and `./docker_volumes/clients_nodejs_node_modules:/home/node/app/clients/nodejs/node_modules`;
  - no `ports`, no `depends_on`, no `command` (a one-off `run` service).

  Check that the image's user can write to the mounted `node_modules` (the `frontend_dev` volume pattern), and set `user: node` if needed.
- `docker_volumes/clients_nodejs_node_modules/.gitkeep`: new, so the folder exists (`.gitignore` keeps `docker_volumes/*/.gitkeep`).
- `Makefile`:
  - `clients-checks:` runs `docker compose run --rm clients clients/scripts/checks.sh`;
  - `tests:` gains `$(MAKE) clients-checks` as its last line;
  - add `clients-checks` to `.PHONY` if the file declares it.

## Files to Change
- `docker-compose.yml`: the `clients` service.
- `docker_volumes/clients_nodejs_node_modules/.gitkeep`: new.
- `Makefile`: the `clients-checks` target; `tests` runs it.
