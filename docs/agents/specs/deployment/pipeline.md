# CI/CD Pipeline

Purpose: define the CircleCI jobs, the workflow and which jobs deploy a release.

CI uses CircleCI (`.circleci/config.yml`) following the patterns of
https://github.com/darthjee/tent and https://github.com/darthjee/majora.

Backend jobs run in the CI image `circleci_pidgey-base` (see
[images](../docker/images.md)), not in `dev_pidgey`. `frontend_checks` and `build_frontend`
run in `darthjee/circleci_node:0.2.1`. The context jobs (`upload_backend`, `upload_frontend`,
`release`) run in `circleci_pidgey-base`, which carries the deploy tooling (see
[images](../docker/images.md#deploy-tooling)).

## Check jobs

| Job | Steps |
| --- | --- |
| `backend_checks` | `composer lint` (phpcs + slevomat, phpmd). |
| `backend_tests` | Service containers `mysql:9` (named `mysql`) and `axllent/mailpit` (named `mailpit`); `composer db:prepare` (wait for the database, create `pidgey_test`, run migrations); `composer coverage` (PHPUnit unit + integration); upload `coverage/clover.xml` to Codacy with a single plain `report` command (no `--partial`/`final`) using `CODACY_PROJECT_TOKEN`. |
| `frontend_checks` | Moves `frontend/` into the working directory, then `yarn install --frozen-lockfile`, `npm run lint`, `npm run coverage` (see [frontend/testing.md](../frontend/testing.md)). |
| `build_images` | Future work. Build `dev_pidgey` (and `dev_pidgey-base` when changed). |

## Deploy jobs

Production is an SSH/rsync host (see [server.md](server.md)). A release is the content of
`source/` (with production Composer dependencies) plus the built frontend in
`public/admin/`, uploaded into a staging folder on the host and then swapped into the live
folder. The step-by-step flow, the deploy script and the CircleCI variables are in
[release.md](release.md).

The deploy chain is split in two kinds of jobs:

- **Build jobs** install third-party dependencies and build the frontend. They run
  **without** the `pidgey-deploy` context, so third-party code (Composer and Yarn packages,
  their scripts and plugins) never runs next to the deploy key. They hand their output to the
  upload jobs through the CircleCI workspace (`persist_to_workspace` / `attach_workspace`).
- **Context jobs** use the `pidgey-deploy` context and run only deploy-script actions
  (`upload`, `link`, `run`, `release`, `cleanup`) on files already built; they install and
  build nothing.

| Job | Context | Image | Steps |
| --- | --- | --- | --- |
| `build_backend` | None | `circleci_pidgey-base` (PHP 8.4) | Keep only `source/`, `composer install --no-dev`, then `persist_to_workspace` the result (with `vendor/`). |
| `build_frontend` | None | `darthjee/circleci_node:0.2.1` | Keep only `frontend/`, `yarn install --frozen-lockfile`, `vite build`, then `persist_to_workspace` `dist/`. |
| `upload_backend` | `pidgey-deploy` | `circleci_pidgey-base` | `attach_workspace`, then `upload` the backend into the staging folder, skipping the paths in `.circleci/backend-exclude.txt`. |
| `upload_frontend` | `pidgey-deploy` | `circleci_pidgey-base` | `attach_workspace`, then `upload` `dist/` into the staging folder's `public/admin/`. |
| `release` | `pidgey-deploy` | `circleci_pidgey-base` | In the staging folder: `link` the shared `.env` (`$REMOTE_HOME/$SHARED_DIR/.env`) to `config/.env`, `run` `$PHP_BIN bin/check-config`, `run` `$PHP_BIN bin/migrate`; then `release` (swap staging into live) and `cleanup` (remove stale staging folders). |

Every job of the deploy chain (build jobs and context jobs):

- runs only on semver tags matching `/^\d+\.\d+\.\d+$/` (no `v` prefix), with all
  branches ignored;
- requires `backend_checks`, `backend_tests` and `frontend_checks` (directly or through the
  jobs it requires), so a tag on a red commit deploys nothing.

Every context job (`upload_backend`, `upload_frontend`, `release`):

- uses the `pidgey-deploy` context, which holds every deploy variable. The context is
  attached only to these jobs **and** restricted in CircleCI, so attaching it in
  `config.yml` is not enough on its own (see [security.md](security.md#ci-secrets)):
  - an expression restriction makes it available only to pipelines on semver tags, e.g.
    `pipeline.git.tag =~ "^[0-9]+\.[0-9]+\.[0-9]+$"`;
  - when available on the plan, a security group containing only the owner;
  - a branch that edits `config.yml` to attach the context gets an unauthorized error;
- belongs to one `serial-group` (`pidgey-deploy`), so deploys run one at a time, in the order
  they were queued. Rerunning or pushing an older tag redeploys that older code; its schema
  stays compatible under the expand/contract rule (see
  [edge-cases.md](edge-cases.md#migrations));
- checks that `SSH_KNOWN_HOSTS` is set before any SSH call and fails otherwise.

Job order:

1. `build_backend` and `build_frontend` run in parallel, after all three check jobs pass.
2. `upload_backend` requires `build_backend`.
3. `upload_frontend` requires `build_frontend` and `upload_backend`, so the two uploads never
   write to the staging folder at the same time.
4. `release` requires both uploads (and, through them, every build and check job).

No production image is built: a release is identified by its git semver tag, and the host
runs the uploaded files.

For now images (`dev_pidgey-base`, `circleci_pidgey-base`) are built and pushed manually
(see [makefile](../docker/makefile.md)); there is no automated image build.

## Database access in CI

`backend_tests` connects to MySQL as **root** (`MYSQL_ROOT_PASSWORD=root` on the service;
`DB_HOST=mysql`, `DB_PORT=3306`, `DB_NAME=pidgey_test`, `DB_USER=root`, `DB_PASSWORD=root`,
`SMTP_HOST=mailpit`, `SMTP_PORT=1025`, `PHP_ENV=test` on the primary container). Why: CircleCI
service containers cannot run the `initdb.d` grant script used locally (see
[compose](../docker/compose.md)), and the tests create both `pidgey_test` and
`pidgey_runner_*_test` databases, which a restricted user could not do. The CI database is
throwaway, so root carries no risk.

## Workflow

- `backend_checks`, `backend_tests` and `frontend_checks` run in parallel on every branch
  **and every tag**.
- The deploy chain (`build_backend`, `build_frontend`, `upload_backend`, `upload_frontend`,
  `release`) runs only on semver tags, after all three check jobs pass. Pushes to `main` or any other branch never deploy.

## Codacy

- Coverage is uploaded by `backend_tests` with `CODACY_PROJECT_TOKEN`, which is set only in
  the CircleCI project settings (never committed).
- Static-analysis exclusions live in `.codacy.yml` at the repository root (`exclude_paths`),
  including `frontend/coverage/**`, `frontend/node_modules/**` and `**/yarn.lock`.

## Settled decisions

- **Hosting target:** an SSH/rsync host. Its connection details are CircleCI variables in
  the `pidgey-deploy` context; nothing about the host is committed (see
  [server.md](server.md)).
- **Migrations on deploy:** `$PHP_BIN bin/migrate` runs in the staging folder, after
  `bin/check-config` and before the swap, so new code never serves an old schema and a
  failed migration leaves the live release untouched (see [release.md](release.md) and
  [edge-cases.md](edge-cases.md)).
- **Image tagging scheme:** dropped. No production image is built; a release is identified
  by its git semver tag.
- **Frontend delivery:** option C, the deploy workflow builds the frontend and uploads it
  into `public/admin/` (recorded in
  [frontend/build.md](../frontend/build.md#production-delivery)).

## Pending decisions

- Whether `frontend_checks` also uploads frontend coverage to Codacy (which would turn the
  backend upload into a partial report plus a final step), or frontend coverage stays out of
  Codacy.
- `build_images` (automated image builds) stays future work; images are built and pushed by
  hand.
