# Docker Images

Purpose: define the Docker image layers used for development, CI and deploys.

Both backend and frontend are developed and tested inside Docker. Layering follows
https://github.com/darthjee/tent `dockerfiles/`.

| Image | Dockerfile | Content |
| --- | --- | --- |
| `dev_pidgey-base` | `dockerfiles/dev_pidgey-base/Dockerfile` | Published base image: `php:8.4-apache`, extensions `zip`, `pdo`, `pdo_mysql`, `pcov` (coverage), Composer, Apache `mod_rewrite`, a non-root `app` user (uid 1000). Multi-arch tags: `<version>` (amd64) and `<version>-arm64`. Rebuilt rarely; built and pushed manually to the registry. |
| `dev_pidgey` | `dockerfiles/dev_pidgey/Dockerfile` | `FROM dev_pidgey-base:<version><BASE_SUFFIX>` (`ARG BASE_SUFFIX` selects the amd64 or `-arm64` tag); copies `composer.json`/`composer.lock` and runs `composer install` (with dev dependencies); installs the pinned, sha256-verified phpDocumentor phar at `/usr/local/bin/phpdoc` (see [dependencies](../architecture/dependencies.md)). Used for local development and the compose services (not CI). |
| `circleci_pidgey-base` | `dockerfiles/circleci_pidgey-base/Dockerfile` | CI image: `cimg/php:8.4` + extensions `zip`, `pdo_mysql`, `pcov`, Composer, project dependencies pre-installed at `/home/circleci/vendor`. amd64 only (single `<version>` tag, no `-arm64`). Also carries the deploy tooling (see [Deploy tooling](#deploy-tooling)). Built from the `source/` context and pushed manually with `make build-ci-base` / `make push-ci-base`. Used by every backend CircleCI job, including the `upload_backend` and `release` deploy jobs. |
| `vite_pidgey-base` | `dockerfiles/vite_pidgey-base/Dockerfile` | Published frontend base image: `darthjee/node` with the yarn cache pre-warmed by `darthjee/scripts` `yarn_builder.sh`; `CMD ["npm", "run", "server"]`. Built from the **repository root** context, since it copies `frontend/package.json`/`frontend/yarn.lock`. Multi-arch tags: `<version>` (amd64) and `<version>-arm64`. Rebuilt rarely; built and pushed manually with `make build-fe-base` / `make push-fe-base`. |
| `vite_pidgey` | `dockerfiles/vite_pidgey/Dockerfile` | `FROM vite_pidgey-base:<version><BASE_SUFFIX>` (`ARG BASE_SUFFIX` selects the amd64 or `-arm64` tag); repository-root context. Used by the `frontend_dev` compose service (see [frontend/development.md](../frontend/development.md)). |

The repository-root build context of `vite_pidgey-base` and `vite_pidgey` is trimmed by the
root `.dockerignore`, which excludes `docker_volumes/`, `.git`, `node_modules`, and build and
coverage output.

Apache serves PHP directly (`apache2-foreground`); there is no Tent or reverse proxy layer.

Registry: Docker Hub `darthjee/`.

There is **no production image**. Production runs from files uploaded by the deploy workflow
to an SSH/rsync host (see [deployment/release.md](../deployment/release.md)), and the built
frontend reaches production by upload (option C in
[frontend/build.md](../frontend/build.md#production-delivery)). A release is identified by its
git semver tag.

## Deploy tooling

The deploy jobs (see [deployment/pipeline.md](../deployment/pipeline.md#deploy-jobs)) need:

- **`deploy_frontend.sh`** from the `darthjee/scripts` image, version `0.11.0` (`0.12.0` once
  released, for `cleanup`; see [deployment/release.md](../deployment/release.md#deploy-script)).
  It is copied in, not vendored:
  `COPY --from=darthjee/scripts:<version> /home/scripts/sbin/deploy_frontend.sh /usr/local/sbin/`.
- **`ssh`** (`openssh-client`) and **`rsync`**.

Images:

- **`circleci_pidgey-base`** gains `deploy_frontend.sh`, `openssh-client` and `rsync`. It runs
  `upload_backend` (PHP 8.4, Composer) and `release`.
- **Frontend deploy image:** `upload_frontend` needs an image with node and yarn (matching
  `frontend_checks`), plus `deploy_frontend.sh`, `ssh` and `rsync`. Whether this extends an
  existing image or is a new `dockerfiles/` image is left to the implementation (#98); a new
  image follows the existing naming and is built and pushed by hand like the others (see
  [makefile.md](makefile.md)).

No image-processing extension is installed: Pidgey does no image processing; one is added only
if a feature needs it.

Node.js/Yarn live in the separate frontend images (`vite_pidgey-base` / `vite_pidgey`), not in
`dev_pidgey-base`, so the PHP and Node toolchains are built and versioned independently.
