# Pipeline

Purpose: describe the CircleCI workflow as defined in `.circleci/config.yml`: which jobs run,
when, in which order, with which secrets, and how they reach the host.

The workflow is `main`. Every job is in it; filters decide which jobs run for a branch or a
tag.

## Check jobs

These run on **every branch and every tag** (`tags: only: /.*/`).

| Job | Image | What it does |
| --- | --- | --- |
| `backend_checks` | `darthjee/circleci_pidgey-base` | `composer lint`. |
| `backend_tests` | `darthjee/circleci_pidgey-base`, plus `mysql:9` and `axllent/mailpit` service containers | `composer db:prepare`, `composer coverage`, then a partial Codacy coverage upload. |
| `frontend_checks` | `darthjee/circleci_node` | `yarn install --frozen-lockfile`, `npm run lint`, `npm run coverage`, then a partial Codacy coverage upload. |
| `coverage_final` | `darthjee/circleci_node` | Requires `backend_tests` and `frontend_checks`; sends the Codacy `final` call. |

### Database access in CI

`backend_tests` connects to MySQL as **root**: the `mysql` service sets
`MYSQL_ROOT_PASSWORD=root`, and the primary container sets `DB_HOST=mysql`, `DB_PORT=3306`,
`DB_NAME=pidgey_test`, `DB_USER=root`, `DB_PASSWORD=root`, `SMTP_HOST=mailpit`,
`SMTP_PORT=1025` and `PHP_ENV=test`.

Why: CircleCI service containers cannot run the `initdb.d` grant script used locally (see
[docker/compose.md](../specs/docker/compose.md)), and the tests create both `pidgey_test` and
`pidgey_runner_*_test` databases, which a restricted user could not do. The CI database is
throwaway, so root carries no risk.

## Codacy

- `backend_tests` and `frontend_checks` each upload their coverage report
  (`coverage/clover.xml` and `coverage/lcov.info`) as a **partial** report, through the
  `upload_codacy_coverage` command. That command first rewrites the report's file paths to be
  repository-relative (`source/…`, `frontend/…`), because each job flattens its folder to the
  working directory's root.
- `coverage_final` sends the Codacy `final` call once both partial reports are in.
- Frontend coverage is therefore in Codacy, next to backend coverage (#75). This settles the
  open question of whether `frontend_checks` should upload it.
- The uploads use `CODACY_PROJECT_TOKEN`, a CircleCI **project** variable (see
  [variables.md](variables.md#where-they-live)); it is never committed.
- Static-analysis exclusions live in `.codacy.yml` at the repository root (`exclude_paths`):
  generated and third-party files (`source/vendor/**`, coverage, phpdoc and PHPUnit caches,
  `frontend/node_modules/**`, `**/yarn.lock`), `docs/**`, `docker_volumes/**` and
  `.claude/**`.

## Deploy chain

These run **only on semver tags**: the filter is `tags: only: /^\d+\.\d+\.\d+$/` with
`branches: ignore: /.*/`. A tag such as `1.2.3` deploys; `v1.2.3`, `1.2` or any branch push
does not.

| Job | Context | Requires | What it does |
| --- | --- | --- | --- |
| `build_backend` | None | `backend_checks`, `backend_tests`, `frontend_checks` | Renames `source/` to `backend/`, runs `composer install --no-dev --optimize-autoloader`, persists `backend/` to the workspace. |
| `build_frontend` | None | `backend_checks`, `backend_tests`, `frontend_checks` | Keeps only `frontend/`, runs `yarn install --frozen-lockfile` and `yarn build`, persists the output as `frontend-dist/`. |
| `upload_backend` | `pidgey-deploy` | `build_backend` | Attaches the workspace, runs `deploy_setup`, uploads `backend/` into the staging folder with the exclude list. |
| `upload_frontend` | `pidgey-deploy` | `build_frontend`, `upload_backend` | Attaches the workspace, runs `deploy_setup`, uploads `frontend-dist/` into the staging folder's `public/admin/`. |
| `release` | `pidgey-deploy` | `upload_backend`, `upload_frontend` | Runs `deploy_setup`, links the shared `.env`, runs `bin/check-config` and `bin/migrate` in staging, swaps staging into live, then runs `cleanup` to remove stale staging folders. |

Order:

1. The three check jobs pass.
2. `build_backend` and `build_frontend` run in parallel.
3. `upload_backend` runs after `build_backend`.
4. `upload_frontend` runs after `build_frontend` **and** `upload_backend`, so the two uploads
   never write to the staging folder at the same time.
5. `release` runs after both uploads.

A tag on a red commit deploys nothing: every deploy job depends, directly or through other
jobs, on all three check jobs.

No production image is built: a release is identified by its git semver tag, and the host runs
the uploaded files. The deploy chain builds the admin UI and uploads it into `public/admin/`
(the frontend delivery decision in
[frontend/build.md](../specs/frontend/build.md#production-delivery)).

## Build jobs and context jobs

- **Build jobs** (`build_backend`, `build_frontend`) have **no context**. They are the only
  jobs that install third-party code (Composer and Yarn packages, with their scripts and
  plugins).
- **Context jobs** (`upload_backend`, `upload_frontend`, `release`) use the `pidgey-deploy`
  context, which holds every deploy variable (see [variables.md](variables.md)). They install
  and build nothing; they only run `deploy_frontend.sh` actions on files already built.

Why: third-party code never runs next to the deploy key. A compromised dependency in a build
job finds no secret to read; at worst it alters the release content, which is the same risk
any dependency carries at runtime. Builds hand their output to the context jobs as plain
files, through the CircleCI workspace (`persist_to_workspace` / `attach_workspace`). Because
the build jobs hold no secret, Composer's `--no-scripts --no-plugins` (and their Yarn
equivalents) are not needed.

## Context restriction

Attaching the context in `config.yml` alone would let any branch that edits the config
reach the deploy variables. The `pidgey-deploy` context is therefore restricted in the
CircleCI organization settings with an expression restriction:

```
pipeline.git.tag =~ "^[0-9]+\.[0-9]+\.[0-9]+$"
```

Only pipelines on semver tags get the context; any other pipeline that attaches it gets an
unauthorized error and no variables. There is no security group on top of it: security
groups are not available on the CircleCI free plan. "Pass secrets to builds from forked pull
requests" stays off (see [variables.md](variables.md#where-they-live)).

Why: the restriction is enforced by CircleCI, outside the repository, so no change to
`config.yml` can lift it. Together with the tag filter, only someone who can push a semver tag
can reach the deploy key (see [Who can deploy](#who-can-deploy)).

## Serial group

The three context jobs share

```yaml
serial-group: << pipeline.project.slug >>/pidgey-deploy
```

so deploys run one at a time, in the order they were queued. Pushing or rerunning an older
tag redeploys that older code.

## `deploy_setup`

Every context job starts with the `deploy_setup` command:

1. **`SSH_KNOWN_HOSTS` guard:** fails the job when `SSH_KNOWN_HOSTS` is unset or empty.
   Without it the deploy script would fall back to `StrictHostKeyChecking=no`, and a machine
   impersonating the host would receive the release and run the deploy commands. Its value comes from a
   verified `ssh-keyscan` (see [variables.md](variables.md#ssh_known_hosts-format)).
2. **`deploy_frontend.sh generate_key_file`:** writes `SSH_PRIVATE_KEY` to `~/ssh_key` and
   `SSH_KNOWN_HOSTS` to `~/ssh_known_hosts` (both mode `600`), turning each literal `\n`
   into a newline (see [variables.md](variables.md)). Every later SSH and rsync call uses
   these files with `StrictHostKeyChecking=yes`.

## Deploy script

The context jobs use `deploy_frontend.sh` from `darthjee/scripts:0.12.0`. The version comes
from the `SCRIPTS_VERSION` build argument of `dockerfiles/circleci_pidgey-base/Dockerfile`,
which copies the script to `/usr/local/sbin/`; bumping the script means bumping that argument
and rebuilding the CI image.

Each workflow uploads into its own staging folder,
`${SSH_REMOTE_TEMP_DIR}-${CIRCLE_WORKFLOW_WORKSPACE_ID}` (see [server.md](server.md)).

Actions used:

| Action | Job | Inputs | What it does |
| --- | --- | --- | --- |
| `generate_key_file` | every context job | `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS` | Writes the key and known-hosts files (see above). |
| `upload` | `upload_backend` | `SOURCE=/tmp/workspace/backend/`, `EXCLUDE_FROM=.circleci/backend-exclude.txt` | Creates the staging folder and rsyncs `SOURCE` into it, skipping the excluded paths. |
| `upload` | `upload_frontend` | `SOURCE=/tmp/workspace/frontend-dist/`, `DEPLOY_PATH=public/admin/` | Rsyncs the built admin UI into staging's `public/admin/`. |
| `link` | `release` | `SOURCE="$REMOTE_HOME/$SHARED_DIR/.env"`, `DEPLOY_PATH=config/.env` | Runs `mkdir -p config` in staging (it already holds the route files) and adds a symlink `config/.env` → the shared `.env` (`ln -sfn`). |
| `run` | `release` | `COMMAND="$PHP_BIN bin/check-config"` | Runs the configuration checks inside staging. |
| `run` | `release` | `COMMAND="$PHP_BIN bin/migrate"` | Applies pending migrations inside staging. |
| `release` | `release` | none | Checks that staging exists, moves live aside to `<live>_old_<timestamp>`, moves staging to live, removes the old folder. |
| `cleanup` | `release` | none (uses the SSH settings, `SSH_REMOTE_TEMP_DIR` and `CIRCLE_WORKFLOW_WORKSPACE_ID`) | Removes other `${SSH_REMOTE_TEMP_DIR}-*` staging folders older than one day (the default `CLEANUP_MAX_AGE_MINUTES=1440`, which Pidgey does not override). The current workflow's folder and younger folders, which may belong to a deploy in progress, are kept. |

`upload_backend` checks out the repository only to read the exclude list; the files it
uploads come from the workspace.

**Migrations on deploy:** `bin/check-config` and `bin/migrate` run in staging, before the
swap. Why: new code never serves an old schema, and a missing `.env` setting or a failed
migration stops the deploy with the live release untouched instead of causing a site-wide
`500` after the swap (see [operations.md](operations.md#failure-before-the-swap)).

**Cleanup:** the `release` job ends with a `Cleanup` step (`deploy_frontend.sh cleanup`), run
after the swap. It is a plain step: if it fails, the job and the workflow fail even though the
new release is already live, so leftover folders get noticed. Fall back to removing them by
hand (see [operations.md](operations.md#removing-stale-staging-folders)).

## Exclude list

`.circleci/backend-exclude.txt` is passed to rsync as `--exclude-from`. It keeps out of the
release:

- `tests/` and `coverage/`;
- lint, phpdoc and PHPUnit configuration and caches: `phpcs.xml`, `phpcs/`, `phpmd*.xml`,
  `phpdoc.dist.xml`, `phpdoc/`, `.phpdoc/`, `phpunit.xml`, `.phpunit.result.cache`,
  `.phpunit.cache`;
- any `.env*` file;
- `.git`.

Everything else under `source/` ships: `bin/`, `config/` (route files; the `release` job adds
the `.env` link), `lib/`, `migrations/`, `public/` (with the built admin UI), `vendor/`
(production dependencies only), `composer.json` and `composer.lock`. Only the live release's
`public/` is web-reachable (see [server.md](server.md#web-directory)).

A new top-level path in `source/` ships too unless it is added to the exclude list. Rule: any
change that adds a top-level path to `source/` adds it, in the same change, either to
`.circleci/backend-exclude.txt` or to the shipped list above. Why: the list is an exclude
list, so anything forgotten reaches the host by default.

## Logs

- `bin/check-config` reports which check failed, never a value (see
  [architecture/configuration.md](../specs/architecture/configuration.md#bincheck-config)).
- The deploy never prints the shared `.env` or any secret: the key files are written by
  `generate_key_file`, and the `.env` is linked on the host, never read by CI.

Why: CircleCI job output is visible to anyone with access to the project, and it is kept.

## Who can deploy

Anyone who can push a semver tag to the repository. Today that is only the owner, the sole
collaborator, so there is no GitHub tag ruleset.

Why this is enough: the deploy chain runs only on semver tags, every deploy job requires every
check job, and the `pidgey-deploy` context is available only to semver-tag pipelines (see
[Context restriction](#context-restriction)). Pushing a branch, or editing `config.yml` on a
branch, never reaches the host.
