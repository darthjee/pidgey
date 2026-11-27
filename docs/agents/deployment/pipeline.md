# Pipeline

Describe the CircleCI workflow as defined in `.circleci/config.yml`: which jobs run,
when, in which order, with which secrets, and how they reach the host.

The workflow is `main`. Every job is in it; filters decide which jobs run for a branch or a
tag. Besides the checks, it holds two release chains: the [deploy chain](#deploy-chain) of the
application and the [npm publish chain](#npm-publish-chain) of the client packages.

## Check jobs

These run on **every branch and every tag** (`tags: only: /.*/`).

| Job | Image | What it does |
| --- | --- | --- |
| `backend_checks` | `darthjee/circleci_pidgey-base` | `composer lint`, then the `OpenAPI breaking-change check` step: `scripts/check_openapi_breaking.sh` compares `source/openapi/v1.json` against its version at the merge-base with `main` (`oasdiff breaking --fail-on ERR`), see [`/v1` is additive-only](../api/endpoints/openapi.md#v1-is-additive-only). The job holds no context. |
| `backend_tests` | `darthjee/circleci_pidgey-base` (which ships the Mailpit binary), plus a `mysql:9` service container | Starts the three test Mailpit instances on `localhost` and waits for them, then `composer db:prepare`, `composer coverage`, then a partial Codacy coverage upload. |
| `frontend_checks` | `darthjee/circleci_node` | `yarn install --frozen-lockfile`, `npm run lint`, `npm run coverage`, then a partial Codacy coverage upload. |
| `clients_checks` | A matrix over two Node.js images: `cimg/node:22.12.0` (the floor of `engines`) and `darthjee/circleci_node:0.2.1` (the current one) | `clients/scripts/checks.sh`, the same script as `make clients-checks`: for the packages `nodejs` and `cli`, the frozen install, `yarn lint`, `yarn typecheck`, `yarn coverage` (100 % threshold) and the pack file-list check, the committed-file lint for `cli`, then the pack smoke test once. A step then asserts that the working tree is unchanged (`git status --porcelain` is empty). The instance on `darthjee/circleci_node:0.2.1` then does two partial Codacy coverage uploads (`clients/nodejs` and `clients/cli`); the floor instance does none. The job holds no context. See [clients.md](../clients.md#checks) and [clients/cli.md](../clients/cli.md). |
| `clients_integration` | A machine executor (`ubuntu-2404`), not a Docker one: the stack is booted with `docker compose` | Requires `clients_checks`. Builds the dev base image from `dockerfiles/dev_pidgey-base` (`make build-base`; `darthjee/dev_pidgey-base` is not published to Docker Hub and the job has no registry credentials), builds the dev image on top of it (`docker compose build base_build`), then runs `make clients-integration`, the same command as locally: it starts `mysql` and `mailpit-plain`, prepares the `pidgey_integration` database, starts `integration_app`, and runs `clients/scripts/integration.sh` (seed through the admin API, then `yarn test:integration` in `clients/nodejs` and `clients/cli`). The job holds no context and uploads no coverage; it does not feed `coverage_final` and the deploy jobs do not require it (the client [pack jobs](#npm-publish-chain) do). See [clients.md](../clients.md#checks). |
| `coverage_final` | `darthjee/circleci_node` | Requires `backend_tests`, `frontend_checks` and `clients_checks`; sends the Codacy `final` call. |

### Database access in CI

`backend_tests` connects to MySQL as **root**: the `mysql` service sets
`MYSQL_ROOT_PASSWORD=root`, and the primary container sets `DB_HOST=mysql`, `DB_PORT=3306`,
`DB_NAME=pidgey_test`, `DB_USER=root`, `DB_PASSWORD=root` and `PHP_ENV=test`.

Why: CircleCI service containers cannot run the `initdb.d` grant script used locally (see
[docker/compose.md](../docker/compose.md)), and the tests create both `pidgey_test` and
`pidgey_runner_*_test` databases, which a restricted user could not do. The CI database is
throwaway, so root carries no risk.

### Email test instances

Rules: [email/testing.md](../email/testing.md#reaching-the-instances) (instances, ports, `TEST_MAILPIT_*`, TLS fixtures); [docker/compose.md](../docker/compose.md).

`backend_tests` runs the three test Mailpit instances as **background run steps** on
`localhost`, not as service containers. The `mailpit` binary (`v1.32.0`, the same tag as
compose) is baked into `darthjee/circleci_pidgey-base` from `0.0.4` on. Each step runs
`mailpit` with:

| Instance | Mode | Auth | SMTP bind | API bind |
| --- | --- | --- | --- | --- |
| plain | plain | none | `0.0.0.0:2025` | `0.0.0.0:9025` |
| STARTTLS | `MP_SMTP_REQUIRE_STARTTLS=true` | `MP_SMTP_AUTH=pidgey-test:pidgey-test-password` | `0.0.0.0:2587` | `0.0.0.0:9587` |
| SSL | `MP_SMTP_REQUIRE_TLS=true` (implicit TLS) | `MP_SMTP_AUTH=pidgey-test:pidgey-test-password` | `0.0.0.0:2465` | `0.0.0.0:9465` |

The two TLS instances set `MP_SMTP_TLS_CERT=tests/fixtures/tls/server.pem` and
`MP_SMTP_TLS_KEY=tests/fixtures/tls/server-key.pem`, relative to the flattened checkout
(the leaf's SAN includes `localhost`).

A `Wait for Mailpit` step then polls `http://localhost:<apiPort>/api/v1/info` for each
instance with `curl -fsS`, up to 30 seconds, and fails the job naming the instance that
never answered, before `composer db:prepare` runs.

Why not service containers: Mailpit reads the TLS certificate and key only from file paths,
and CircleCI service containers cannot mount files from the checkout.

The primary container's env sets:

| Key | Value |
| --- | --- |
| `TEST_MAILPIT_PLAIN` | `localhost:2025:9025` |
| `TEST_MAILPIT_STARTTLS` | `localhost:2587:9587` |
| `TEST_MAILPIT_SSL` | `localhost:2465:9465` |
| `SMTP_ALLOW_PRIVATE_HOSTS` | `true` |
| `SMTP_CA_FILE` | `tests/fixtures/tls/ca.pem` |
| `SMTP_ENCRYPTION_KEY` | a test-only key |

The `TEST_MAILPIT_*` keys (form `host:smtpPort:apiPort`) are read only by
`Pidgey\Tests\Support\Mailpit`; they are test-only, not deployment variables.

## Codacy

- `backend_tests` and `frontend_checks` each upload their coverage report
  (`coverage/clover.xml` and `coverage/lcov.info`) as a **partial** report, through the
  `upload_codacy_coverage` command. That command first rewrites the report's file paths to be
  repository-relative (`source/…`, `frontend/…`), because each job flattens its folder to the
  working directory's root.
- `clients_checks` uploads `clients/nodejs/coverage/lcov.info` and
  `clients/cli/coverage/lcov.info` the same way, with the prefixes `clients/nodejs` and
  `clients/cli`, but only from the instance on `darthjee/circleci_node:0.2.1`. Why: one
  partial report per package is enough, and the floor instance runs the same specs on the
  same code.
- `coverage_final` sends the Codacy `final` call once every partial report is in; it requires
  `backend_tests`, `frontend_checks` and `clients_checks` (every instance of the matrix).
- Both the partial uploads and the `final` call go through the `codacy_reporter` command, which
  downloads the reporter script with `curl --fail --retry` and retries the whole download and
  upload up to 3 times with a short backoff, so a transient network error (e.g. an HTTP/2
  framing error) does not fail the job; a persistent failure still fails it.
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
| `upload_frontend` | `pidgey-deploy` | `build_frontend`, `upload_backend` | Attaches the workspace, runs `deploy_setup`, uploads `frontend-dist/` into the staging folder's `static/`. |
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
the uploaded files. The deploy chain builds the UI and uploads it into `static/`
(the frontend delivery decision in
[frontend/build.md](../frontend/build.md#production-delivery)).

## npm publish chain

Publishes the client packages to the public npm registry. Each package is released by its own
tag, and its jobs run **only on that tag** (`branches: ignore: /.*/`):

| Package | Tag filter | Jobs |
| --- | --- | --- |
| `pidgey-client` (`clients/nodejs/`) | `/^pidgey-client-v\d+\.\d+\.\d+$/` | `pack_client`, `publish_client` |
| `pidgey-cli` (`clients/cli/`) | `/^pidgey-cli-v\d+\.\d+\.\d+$/` | `pack_cli`, `publish_cli` |

Neither filter matches the other, nor the deploy filter `/^\d+\.\d+\.\d+$/`: a client tag
never deploys the backend, and a deploy tag never publishes to npm.

| Job | Context | Requires | What it does |
| --- | --- | --- | --- |
| `pack_client` / `pack_cli` | None | `backend_checks`, `backend_tests`, `frontend_checks`, `clients_checks` (every instance of the matrix), `clients_integration` | `clients/scripts/release_pack.sh "$CIRCLE_TAG" release`, then persists `release/` (the tarball) to the workspace. |
| `publish_client` / `publish_cli` | `pidgey-npm` | Its pack job | Checks out the repository (the scripts and the package sources come from git), attaches the workspace at `/tmp/workspace`, fails unless it holds exactly one `release/*.tgz`, then runs `clients/scripts/release_publish.sh "$CIRCLE_TAG" <tarball>`. |

The four workflow entries use two job definitions, `pack_package` and `publish_package`, on
the executor `client-release` (`cimg/node:22.12.0`, the floor of the packages' `engines`,
with npm 10.9.0).

### Pack script

`release_pack.sh <tag> <out_dir>` fails unless, in order:

1. the tag is `pidgey-client-vX.Y.Z` or `pidgey-cli-vX.Y.Z`;
2. the tag version equals `version` in the package's `package.json`;
3. the package's `CHANGELOG.md` has a heading `## X.Y.Z` (optionally followed by a date);
4. `yarn install --frozen-lockfile --ignore-scripts` succeeds, and for the library
   `yarn build:types` (the `.d.ts` files of `dist/`);
5. the package packs to `<out_dir>/<name>-<version>.tgz` (the CLI through `pack_cli.sh`, so
   its dependency on the library is `^<library version>`);
6. the file list of that tarball equals the package's `pack-files.txt`.

It prints the tarball path.

### Publish script

`release_publish.sh [--dry-run] <tag> <tarball>` does not trust the tarball, which was built
in a job that runs third-party code. npm is never given it, and nothing is decided from its
`package.json`. The script:

1. takes the package and version from the **tag**, and checks them against the `package.json`
   of the checkout;
2. packs a **reference** tarball from the checkout, the same way as the pack script. The only
   content taken from the received tarball is the library's `dist/`, from a private copy that
   may hold regular files only;
3. fails unless the reference's file list equals `pack-files.txt` and the reference is
   **byte-identical** to the received tarball;
4. ends as a **no-op success** if `npm view <name>@<version>` already answers. Any npm error
   other than "not found" fails the job;
5. for the CLI, polls npm for the library floor (the `^X.Y.Z` it depends on) every 10 s for
   up to 5 minutes;
6. publishes the reference (the same bytes) with
   `npm publish <tarball> --access public --ignore-scripts`, without `--provenance` (see
   [known-gaps.md](known-gaps.md#npm-packages-published-without-provenance));
7. polls every 10 s, for up to 5 minutes, until npm serves the new version.

The token is written to no file: a temporary npm user configuration holds the literal
`${NPM_TOKEN}`, which npm expands itself, and only the publish command uses it. No release
script enables shell tracing.

The byte comparison needs `npm pack` to give the same bytes in both jobs, so both run on the
same executor and both pack from a staging copy with normalized file modes. A difference fails
the publish; nothing is published.

### Dry run

`--dry-run` runs the same path without publishing: the same checks, comparison and
"already published" no-op; the CLI floor is checked once and a missing floor is only a
warning; `npm publish --dry-run` runs without a token; there is no wait for npm. CI never
passes it. See [operations.md](operations.md#dry-run-a-client-release-locally).

## Build jobs and context jobs

- **Build jobs** (`build_backend`, `build_frontend`, `pack_client`, `pack_cli`) have **no
  context**. Among the release jobs, they are the only ones that install third-party code
  (Composer and Yarn packages). The deploy build jobs run their scripts and plugins; the pack
  jobs install with `--ignore-scripts`.
- **Deploy context jobs** (`upload_backend`, `upload_frontend`, `release`) use the
  `pidgey-deploy` context, which holds every deploy variable (see
  [variables.md](variables.md)). They install and build nothing; they only run
  `deploy_frontend.sh` actions on files already built.
- **Publish jobs** (`publish_client`, `publish_cli`) use the `pidgey-npm` context, which holds
  only the npm token. They install nothing and run no package lifecycle script; they run the
  committed release scripts and the tools of the image.

Why: third-party code never runs next to the deploy key or the npm token. A compromised
dependency in a build job finds no secret to read; at worst it alters the release content,
which is the same risk any dependency carries at runtime. For the npm packages that is
narrower still, since the publish job rebuilds the tarball from the checkout (see
[Publish script](#publish-script); the exception is in
[known-gaps.md](known-gaps.md#npm-type-declarations-are-not-verified-at-publish)). Builds hand
their output to the context jobs as plain files, through the CircleCI workspace
(`persist_to_workspace` / `attach_workspace`). Because the deploy build jobs hold no secret,
Composer's `--no-scripts --no-plugins` (and their Yarn equivalents) are not needed there.

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

The `pidgey-npm` context is a separate context, so the deploy key and the npm token are never
reachable from the same job. The maintainer creates it (see
[operations.md](operations.md#set-up-npm-publishing)) with two restrictions:

- a **project restriction** to the Pidgey project. Without one, every project of the CircleCI
  organization can use a context;
- an **expression restriction**:

  ```
  pipeline.git.tag =~ "^pidgey-(client|cli)-v[0-9]+\.[0-9]+\.[0-9]+$" and not job.ssh.enabled
  ```

  Only pipelines on client release tags get the token, and an SSH rerun of a publish job does
  not. The CircleCI documentation lists `matches /…/` as its regular-expression operator and
  does not list `=~`; if the settings reject the expression, write the same pattern with
  `matches`. A rejected or false expression fails closed: the job gets no variables.

## Serial group

The three deploy context jobs share

```yaml
serial-group: << pipeline.project.slug >>/pidgey-deploy
```

so deploys run one at a time. Pushing or rerunning an older tag redeploys that older code.

The two publish jobs share their own group,

```yaml
serial-group: << pipeline.project.slug >>/pidgey-npm
```

so publishes run one at a time.

In both groups, CircleCI starts jobs in the order they join the queue, but **skips** a job
that tries to join a busy group with a lower pipeline number than the job waiting or running.
For the npm group this matters when both packages are released: `publish_cli` holds the group
while it waits for the library. Release the library first and push the CLI tag only after
`publish_client` succeeded (see [operations.md](operations.md#release-a-client-package) and
[known-gaps.md](known-gaps.md#releasing-both-clients-at-once)).

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
| `upload` | `upload_frontend` | `SOURCE=/tmp/workspace/frontend-dist/`, `DEPLOY_PATH=static/` | Rsyncs the built UI into staging's `static/`. |
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
the `.env` link), `lib/`, `migrations/`, `public/`, `vendor/`
(production dependencies only), `composer.json` and `composer.lock`. Next to `public/`, the
release also holds `static/`: the built frontend, uploaded by `upload_frontend`, not by
`upload_backend`. Only the live release's `public/` is web-reachable; `static/` is not, and its
files are served through `public/index.php` (see [server.md](server.md#web-directory)).

A new top-level path in `source/` ships too unless it is added to the exclude list. Rule: any
change that adds a top-level path to `source/` adds it, in the same change, either to
`.circleci/backend-exclude.txt` or to the shipped list above. Why: the list is an exclude
list, so anything forgotten reaches the host by default.

## Logs

- `bin/check-config` reports which check failed, never a value (see
  [architecture/configuration.md](../architecture/configuration.md#bincheck-config)).
- The deploy never prints the shared `.env` or any secret: the key files are written by
  `generate_key_file`, and the `.env` is linked on the host, never read by CI.

Why: CircleCI job output is visible to anyone with access to the project, and it is kept.

## Who can deploy

Anyone who can push a semver tag to the repository. Today that is only the owner, the sole
collaborator, so there is no GitHub tag ruleset.

Why this is enough: the deploy chain runs only on semver tags, every deploy job requires the
backend and frontend check jobs, and the `pidgey-deploy` context is available only to
semver-tag pipelines (see [Context restriction](#context-restriction)). Pushing a branch, or
editing `config.yml` on a branch, never reaches the host.

## Who can publish

Anyone who can push a client release tag: today only the owner, the same access model as the
deploy. This includes any token, app or agent with push access to the repository. The pipeline
configuration comes from the tagged commit, and the tag may point at any commit, so the tag
push is the trust boundary (see
[known-gaps.md](known-gaps.md#client-tags-are-not-protected)). Pushing a branch never gets the
npm token.
