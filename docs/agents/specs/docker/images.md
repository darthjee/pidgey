# Docker Images

Purpose: define the Docker image layers used for development, CI and production.

Both backend and frontend are developed and tested inside Docker. Layering follows
https://github.com/darthjee/tent `dockerfiles/`.

| Image | Dockerfile | Content |
| --- | --- | --- |
| `dev_pidgey-base` | `dockerfiles/dev_pidgey-base/Dockerfile` | Published base image: `php:8.x-apache`, extensions `pdo_mysql`, `zip`, `gd`, `pcov` (coverage), Composer, Apache `mod_rewrite`, a non-root app user. Also includes Node.js + Yarn for the frontend tooling. Rebuilt rarely and pushed to the registry. |
| `dev_pidgey` | `dockerfiles/dev_pidgey/Dockerfile` | `FROM dev_pidgey-base`; copies `composer.json`/`composer.lock` and runs `composer install` (with dev dependencies). Used for development and CI. |
| `pidgey` | `dockerfiles/pidgey/Dockerfile` | Production image: `php:8.x-apache` with `pdo_mysql`, `composer install --no-dev`, application source and the built frontend `dist/` in the web root. Runs `apache2-foreground`. No dev tooling, no pcov. |

Apache serves PHP directly (`apache2-foreground`); there is no Tent or reverse proxy layer.

**Pending decisions:**
- Exact PHP minor version (8.3 or 8.4).
- Registry/namespace for published images (Docker Hub `darthjee/` assumed).
- Whether Node.js lives in `dev_pidgey-base` or in a separate frontend image (working assumption: same base).
