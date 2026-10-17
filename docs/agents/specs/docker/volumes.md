# Volumes

Purpose: define what is mounted into containers and where persistent data lives.

| Host path | Container path | Purpose |
| --- | --- | --- |
| `./source` | `/home/app/app` (app root) | Backend source, live-edited. |
| `./frontend` | frontend working dir | Frontend source, live-edited. |
| `./docker_volumes/mysql_data` | `/var/lib/mysql` | MySQL persistence. |
| `./docker_volumes/vendor` | `<app>/vendor` | Composer vendor, kept out of the source tree. |
| `./docker_volumes/node_modules` | `frontend/node_modules` | Yarn dependencies. |
| `./docker_volumes/configuration` | configuration dir | Local env/config files. |
| `./docker_volumes/cache` | cache dir | Composer/Yarn caches. |
| `./frontend/dist` | `<web root>/static` | Built frontend served as static by Apache. |

`docker_volumes/` is git-ignored except for placeholder files keeping the folders.
Exact container paths are fixed when the Dockerfiles are written.
