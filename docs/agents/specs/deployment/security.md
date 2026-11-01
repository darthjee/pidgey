# Deployment Security

Purpose: record the security decisions for deploying Pidgey to production, and the known gaps.

## Host keys

- `SSH_KNOWN_HOSTS` is **required**. Every context job checks that it is set before any SSH
  call and fails otherwise, because the deploy script would fall back to
  `StrictHostKeyChecking=no` without it.
- Its value comes from `ssh-keyscan`, with the fingerprint checked against the host's own
  before it is saved in CircleCI.

## Deploy key

- A dedicated ed25519 key, used only for Pidgey and authorized only for Pidgey's own host
  user.
- Its `authorized_keys` entry uses `restrict` (no forwarding, no pty).
- There is no `from=` restriction, because CircleCI's IP addresses are not stable.

## CI secrets

- `SSH_*`, `REMOTE_HOME`, `SHARED_DIR` and `PHP_BIN` live in a CircleCI context, `pidgey-deploy`, attached
  only to the context jobs `upload_backend`, `upload_frontend` and `release` (see
  [pipeline.md](pipeline.md#deploy-jobs)). Check jobs and build jobs never see them.
- Attaching the context only in `config.yml` is **not sufficient on its own**: anyone who can
  push a branch could edit the config to attach it. The context is therefore restricted in
  CircleCI's settings:
  - an **expression restriction** makes it available only to pipelines on semver tags, e.g.
    `pipeline.git.tag =~ "^[0-9]+\.[0-9]+\.[0-9]+$"`;
  - if the plan offers it, a **security group** containing only the owner;
  - a branch pipeline whose config attaches the context gets an unauthorized error and no
    variables.
- Passing secrets to builds from forked pull requests stays **off**.
- App settings (`SECRET_KEY`, `DB_*`, …) are never CircleCI variables; they live only in the
  shared `.env` on the host.

## Third-party code

Third-party code never runs next to the deploy key.

- Composer and Yarn packages (and their install scripts and plugins) run only in the build
  jobs, `build_backend` (`composer install --no-dev`) and `build_frontend`
  (`yarn install --frozen-lockfile`, `vite build`), which run **without** the
  `pidgey-deploy` context.
- Their output reaches the context jobs only as files, through the CircleCI workspace
  (`persist_to_workspace` / `attach_workspace`). The context jobs run only deploy-script
  actions (`upload`, `link`, `run`, `release`, `cleanup`) and install or build nothing.
- Why: a compromised dependency could otherwise read `SSH_PRIVATE_KEY` from the job's
  environment. In the build jobs there is no secret to steal; at worst it alters the release
  content, which is the same risk any dependency carries at runtime.
- Because the build jobs hold no secret, Composer's `--no-scripts --no-plugins` (and their
  Yarn equivalents) are not required.

## Who can deploy

- Only semver tags deploy (see [pipeline.md](pipeline.md#deploy-jobs)).
- A GitHub tag ruleset limits creation of semver tags to the owner.
- Every job of the deploy chain requires every check job, so a tag on a red commit deploys
  nothing.
- The `pidgey-deploy` context is available only to semver-tag pipelines (see
  [CI secrets](#ci-secrets)).

## Shared `.env`

- The shared folder is mode `700` and `.env` is mode `600`, both owned by the host user PHP
  runs as (see [server.md](server.md#shared-folder)).
- It is linked into each release at `config/.env`, outside `public/`, so it is never
  web-reachable.
- It never goes through CI.

## What ships

The backend upload excludes tests, coverage, lint/phpdoc config, `phpunit.xml`, any `.env*`
file and `.git` (list in [release.md](release.md#backend-upload-upload_backend)). Only runtime
code, `vendor/`, `public/` (with the built admin UI) and migrations reach the host, and only
the live release's `public/` is web-reachable. Any new top-level path in `source/` must be
added to the exclude list or to this list in the same change (see
[release.md](release.md#backend-upload-upload_backend)).

## Database users

Decision (#109): production uses **two MySQL users** on the same database, so the web app runs
without DDL rights (see [architecture/database.md](../architecture/database.md#database-users)
for which entry point uses which user).

| User | Settings | Grants on the app database | Used by |
| --- | --- | --- | --- |
| Migration user | `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` | `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `INDEX`, `ALTER`, `REFERENCES` | `bin/migrate`, `bin/create-db`, `bin/drop-db` |
| App user | `DB_USER` / `DB_PASSWORD` | `SELECT`, `INSERT`, `UPDATE`, `DELETE` only | the web app, `bin/wait-for-db` |

- `REFERENCES` is needed because migrations create foreign keys.
- Because the migration user has `DROP`, **`bin/drop-db` must never be run in production**;
  it exists only for development and test resets (`make db-reset`).
- Both users' credentials live only in the shared `.env` (see [Shared `.env`](#shared-env));
  nothing about them is committed.
- The code supports the split from the release that ships #109 on. Until production is
  switched over, `DB_MIGRATE_*` is unset and both entry-point groups use `DB_USER` through the
  fallback.
- **Switching production over is a manual step done by the owner**, after that release ships,
  in this order:
  1. Add `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` to the shared `.env`, with the existing
     (DDL-capable) user's credentials.
  2. Switch `DB_USER` / `DB_PASSWORD` to the data-only user.
  3. Verify that the site and the next deploy's `bin/migrate` both work.

  The order matters: switching `DB_USER` before `DB_MIGRATE_*` exists would make the next
  deploy's migrations run as the data-only user and fail.

## Logs

- `bin/check-config` reports which check failed, never a value (see
  [architecture/configuration.md](../architecture/configuration.md#bincheck-config)).
- The deploy never prints `.env` or any secret.

## HTTPS

The host already redirects HTTP to HTTPS.

Decision (#110): `source/public/.htaccess` sends `Strict-Transport-Security: max-age=300` on
HTTPS responses only.

- It covers the API and the admin UI under `/admin/`, which is deployed into `public/admin/`
  and so inherits the same `.htaccess`. Because it is set by the web server, static admin files
  get it too.
- It is sent only over HTTPS; plain-HTTP responses (including local docker and CI, which serve
  plain HTTP) never carry it.
- There is no `includeSubDomains` and no `preload`, because other subdomains on the domain are
  not owned by Pidgey.
- `max-age=300` is the first step of a staged rollout, so a misconfiguration can be undone
  within minutes. #115 raises it to one year after the header is checked on the host.
- Verification after a release: `curl -sI https://<domain>/health.json` and
  `curl -sI https://<domain>/admin/` both show the header.

## Known gaps

- **Auth rate limiting:** the shared host has no configurable reverse proxy, so the
  `/auth/*.json` proxy rate limit recommended in [production.md](production.md) cannot be
  applied there. Production keeps `USER_APPROVAL_REQUIRED=true` to limit registration abuse.

## Pending decisions

- **Pending decision — auth rate limiting:** the proxy rate limit is not available on this
  host; application-level throttling is pending in
  [api/pending.md](../api/pending.md). Until then, keep `USER_APPROVAL_REQUIRED=true` in
  production.
