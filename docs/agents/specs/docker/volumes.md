# Volumes

Purpose: define what is mounted into containers and where persistent data lives.

| Host path | Container path | Purpose |
| --- | --- | --- |
| `./source` | `/home/app/app` (app root) | Backend source, live-edited. |
| `./docker_volumes/mysql_data` | `/var/lib/mysql` | MySQL persistence. |
| `./docker_volumes/vendor` | `<app>/vendor` | Composer vendor, kept out of the source tree. |
| `./frontend` | `/home/node/app` (frontend working dir) | Frontend source, live-edited (`frontend_dev`). |
| `./docker_volumes/node_modules` | `/home/node/app/node_modules` | Yarn dependencies (`frontend_dev`), installed by `make fe-install`; hides the image's install. |
| `./docker_volumes/configuration` | configuration dir | Future work. Local env/config files. |
| `./docker_volumes/cache` | cache dir | Future work. Composer/Yarn caches. |

Rows marked "Future work" are kept as the target design but are not part of the initial
setup.

There is no volume for the built frontend: `make build-fe-release` writes it to
`source/public/admin`, which `app` already serves at `/admin/` through the `./source` mount
(see [frontend/build.md](../frontend/build.md)).

`docker_volumes/` is git-ignored except for placeholder files keeping the folders.
Exact container paths are fixed when the Dockerfiles are written.
