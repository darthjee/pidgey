# Deployment Security

Purpose: record the security decisions for deploying Pidgey to production, and the known gaps.

## Host keys

- `SSH_KNOWN_HOSTS` is **required**. Every deploy job checks that it is set before any SSH
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
  only to the deploy jobs (see [pipeline.md](pipeline.md#deploy-jobs)). Check jobs never see
  them.
- Passing secrets to builds from forked pull requests stays **off**.
- App settings (`SECRET_KEY`, `DB_*`, …) are never CircleCI variables; they live only in the
  shared `.env` on the host.

## Who can deploy

- Only semver tags deploy (see [pipeline.md](pipeline.md#deploy-jobs)).
- A GitHub tag ruleset limits creation of semver tags to the owner.
- Deploy jobs require every check job, so a tag on a red commit deploys nothing.

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
`public/` is web-reachable.

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
