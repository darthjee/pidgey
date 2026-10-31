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

- `SSH_*`, `REMOTE_HOME` and `PHP_BIN` live in a CircleCI context, `pidgey-deploy`, attached
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

## Logs

- `bin/check-config` reports which check failed, never a value (see
  [architecture/configuration.md](../architecture/configuration.md#bincheck-config)).
- The deploy never prints `.env` or any secret.

## HTTPS

The host already redirects HTTP to HTTPS.

## Known gaps

- **Database user:** v1 uses one database user with DDL rights for both migrations and the
  app.
- **Auth rate limiting:** the shared host has no configurable reverse proxy, so the
  `/auth/*.json` proxy rate limit recommended in [production.md](production.md) cannot be
  applied there. Production keeps `USER_APPROVAL_REQUIRED=true` to limit registration abuse.

## Pending decisions

- **Pending decision — migration DB user:** whether to add a separate least-privilege DB user
  (DDL rights for migrations only, data access only for the app). v1 uses one user.
- **Pending decision — HSTS:** whether the app or host sends a `Strict-Transport-Security`
  header. Left out for now.
- **Pending decision — auth rate limiting:** the proxy rate limit is not available on this
  host; application-level throttling is pending in
  [api/pending.md](../api/pending.md). Until then, keep `USER_APPROVAL_REQUIRED=true` in
  production.
