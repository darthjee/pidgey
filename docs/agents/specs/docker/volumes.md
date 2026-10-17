# Volumes

Purpose: define what is mounted into containers and where persistent data lives.

| Host path | Container path | Purpose |
| --- | --- | --- |
| `./source` | `/home/app/app` (app root) | Backend source, live-edited. |
| `./docker_volumes/mysql_data` | `/var/lib/mysql` | MySQL persistence. |
| `./docker_volumes/vendor` | `<app>/vendor` | Composer vendor, kept out of the source tree. |
| `./frontend` | frontend working dir | Future work. Frontend source, live-edited. |
| `./docker_volumes/node_modules` | `frontend/node_modules` | Future work. Yarn dependencies. |
| `./docker_volumes/configuration` | configuration dir | Future work. Local env/config files. |
| `./docker_volumes/cache` | cache dir | Future work. Composer/Yarn caches. |
| `./frontend/dist` | `<web root>/static` | Future work. Built frontend served as static by Apache. |

Rows marked "Future work" are kept as the target design but are not part of the initial
backend setup.

`docker_volumes/` is git-ignored except for placeholder files keeping the folders.
Exact container paths are fixed when the Dockerfiles are written.
