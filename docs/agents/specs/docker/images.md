# Docker Images

Purpose: define the Docker image layers used for development, CI and production.

Both backend and frontend are developed and tested inside Docker. Layering follows
https://github.com/darthjee/tent `dockerfiles/`.

| Image | Dockerfile | Content |
| --- | --- | --- |
| `dev_pidgey-base` | `dockerfiles/dev_pidgey-base/Dockerfile` | Published base image: `php:8.4-apache`, extensions `zip`, `pdo`, `pdo_mysql`, `pcov` (coverage), Composer, Apache `mod_rewrite`, a non-root `app` user (uid 1000). Multi-arch tags: `<version>` (amd64) and `<version>-arm64`. Rebuilt rarely; built and pushed manually to the registry. |
| `dev_pidgey` | `dockerfiles/dev_pidgey/Dockerfile` | `FROM dev_pidgey-base:<version><BASE_SUFFIX>` (`ARG BASE_SUFFIX` selects the amd64 or `-arm64` tag); copies `composer.json`/`composer.lock` and runs `composer install` (with dev dependencies); installs the pinned, sha256-verified phpDocumentor phar at `/usr/local/bin/phpdoc` (see [dependencies](../architecture/dependencies.md)). Used for local development and the compose services (not CI). |
| `circleci_pidgey-base` | `dockerfiles/circleci_pidgey-base/Dockerfile` | CI image: `cimg/php:8.4` + extensions `zip`, `pdo_mysql`, `pcov`, Composer, project dependencies pre-installed at `/home/circleci/vendor`. amd64 only (single `<version>` tag, no `-arm64`). Built from the `source/` context and pushed manually with `make build-ci-base` / `make push-ci-base`. Used by every CircleCI job. |
| `pidgey` | `dockerfiles/pidgey/Dockerfile` | Production image (future work — not part of the initial setup): `php:8.4-apache` with `pdo_mysql`, `composer install --no-dev`, application source and the built frontend `dist/` in the web root. Runs `apache2-foreground`. No dev tooling, no pcov. |

Apache serves PHP directly (`apache2-foreground`); there is no Tent or reverse proxy layer.

Registry: Docker Hub `darthjee/`.

No image-processing extension is installed: Pidgey does no image processing; one is added only
if a feature needs it.

**Pending decisions:**
- Node.js/Yarn are deferred to the frontend setup and not installed yet. Whether they then live
  in `dev_pidgey-base` or in a separate frontend image is open (working assumption: same base).
