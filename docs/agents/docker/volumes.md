# Volumes

| Host path | Container path | Purpose |
| --- | --- | --- |
| `./source` | `/home/app/app` (app root) | Backend source, live-edited. |
| `./docker_volumes/mysql_data` | `/var/lib/mysql` | MySQL persistence. |
| `./docker_volumes/vendor` | `<app>/vendor` | Composer vendor, kept out of the source tree. |
| `./frontend` | `/home/node/app` (frontend working dir) | Frontend source, live-edited (`frontend_dev`). |
| `./docker_volumes/node_modules` | `/home/node/app/node_modules` | Yarn dependencies (`frontend_dev`), installed by `make fe-install`; hides the image's install. |
| `./clients` | `/home/node/app/clients` | Client packages and `clients/scripts/`, live-edited (`clients`). |
| `./docker_volumes/clients_nodejs_node_modules` | `/home/node/app/clients/nodejs/node_modules` | Yarn dependencies of `clients/nodejs/` (`clients`), installed by `checks.sh`; keeps them out of the source tree. |
| `./docker_volumes/clients_cli_node_modules` | `/home/node/app/clients/cli/node_modules` | Yarn dependencies of `clients/cli/` (`clients`), installed by `checks.sh`; keeps them out of the source tree. |
| `./docker_volumes/configuration` | configuration dir | Future work. Local env/config files. |
| `./docker_volumes/cache` | cache dir | Future work. Composer/Yarn caches. |

Rows marked "Future work" are not set up yet.

There is no volume for the built frontend: `make build-fe-release` writes it to
`source/static/`, which `app` already serves at `/` through the `./source` mount
(see [frontend/build.md](../frontend/build.md)).

`docker_volumes/` is git-ignored except for placeholder files keeping the folders.
Exact container paths are fixed when the Dockerfiles are written.
