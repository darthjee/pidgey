# Operations

Purpose: runbooks for releasing Pidgey and for the manual tasks the deploy does not automate.

Placeholders: `<live>` is the live folder (`$SSH_REMOTE_DIR`), `<domain>` is Pidgey's domain.
Commands on the host run over SSH as the host user (see [variables.md](variables.md)).

## Release

1. Make sure the commit to release is on `main` and its CI is green.
2. Tag it with a bare semver tag (`X.Y.Z`, no `v` prefix) and push the tag:

   ```bash
   git tag X.Y.Z <commit>
   git push origin X.Y.Z
   ```

3. Watch the `main` workflow for the tag in CircleCI: the check jobs, then `build_backend`,
   `build_frontend`, `upload_backend`, `upload_frontend` and `release` (see
   [pipeline.md](pipeline.md#deploy-chain)).
4. Verify the release:
   - `https://<domain>/health.json` returns `200`;
   - unknown paths return `404`:
     `curl -s -o /dev/null -w '%{http_code}' https://<domain>/does-not-exist.json` prints `404`;
   - `https://<domain>/admin/` loads;
   - the HSTS header is sent: `curl -sI https://<domain>/health.json` and
     `curl -sI https://<domain>/admin/` both show `Strict-Transport-Security: max-age=86400`;
   - the migrations are applied: `cd <live> && $PHP_BIN bin/migrate --status` lists no
     pending migration;
   - `bin/`, `vendor/` and `config/` are not web-reachable (for example
     `https://<domain>/config/.env` and `https://<domain>/vendor/autoload.php` do not return
     their content).

There is no automated test of the deploy workflow itself: it only runs against the real host.
Besides this check, it is covered by the `darthjee/scripts` test suite (the script's
behaviour) and by the pre-swap guards (`SSH_KNOWN_HOSTS` set, `bin/check-config`,
`bin/migrate`, and the `release` check that staging exists).

## Rerun a failed workflow

Use **Rerun from failed** in CircleCI. It keeps `CIRCLE_WORKFLOW_WORKSPACE_ID`, so the rerun
refills the same staging folder instead of starting a new one. Every step is safe to repeat:
`upload` (rsync), `link` (`ln -sfn`), `bin/check-config` and `bin/migrate` (already applied
migrations are skipped).

Rerunning an older workflow, or pushing an older tag, redeploys that older code over the
current one. That works because every migration keeps working with the previous release's code
(expand first, contract in a later release; see
[architecture/database.md](../specs/architecture/database.md)).

## Failure before the swap

When `upload_*`, `bin/check-config` or `bin/migrate` fails, the `release` action never runs
and the live release is untouched. Read the job output (`bin/check-config` names each failed
check, never a value), fix the cause and push a new tag. Fix a shared `.env` problem on the
host, then rerun the workflow instead.

A failed migration stops at the first failing file; each file is atomic. Fix it with a **new**
migration in a new tag, never by editing an applied one.

Migrations run before the swap, so a failure after them (or between them and the swap) leaves
the old release running on the new schema. That is safe because every migration keeps working
with the previous release's code (expand first, contract in a later release; see
[architecture/database.md](../specs/architecture/database.md#compatibility-with-the-previous-release)).

## Failure in `Cleanup`

When the `Cleanup` step fails, the swap already happened: the new release is live and the
workflow is marked failed only so the leftover folders get noticed. Do not rerun the workflow
from failed, since that reruns the whole `release` job and staging no longer exists. Read the
job output, fix the cause, and remove the stale folders by hand (see
[Removing stale staging folders](#removing-stale-staging-folders)).

## Broken swap

`release` runs, in one SSH command:

1. check that staging exists;
2. remove any leftover `<live>_old_<ts>`, then move `<live>` to `<live>_old_<ts>`;
3. move staging to `<live>`;
4. remove `<live>_old_<ts>`.

If the chain breaks between steps 2 and 3, `<live>` does not exist and the site is down. Look
at the folders on the host:

| State | Meaning | Action |
| --- | --- | --- |
| `<live>` exists, no `<live>_old_*` | The swap completed (or never started). | None. |
| `<live>` missing, `<live>_old_<ts>` and staging exist | Live was moved aside, staging was not moved in. | Restore: `mv <live>_old_<ts> <live>`. |
| `<live>` exists, `<live>_old_<ts>` exists | The swap completed but the old folder was not removed. | Remove `<live>_old_<ts>` by hand. |

After restoring, fix the cause and push a new tag (or rerun the workflow).

Even a successful swap leaves a window of a few milliseconds where `<live>` does not exist and
requests fail. This is accepted.

## Removing stale staging folders

The `Cleanup` step of the `release` job removes staging folders older than one day after each
successful swap (see [pipeline.md](pipeline.md#deploy-script)). Use this runbook as a fallback:
when the `Cleanup` step failed, or to remove a folder younger than one day that is known to be
dead (for example, left by a failed workflow you will not rerun).

1. List them: `ls -d "$SSH_REMOTE_TEMP_DIR"-*` (with the variable's value).
2. Check in CircleCI that no deploy workflow is running. Never remove a folder that belongs
   to a running workflow, and keep the folder of a workflow you still plan to rerun from
   failed.
3. Remove the rest with `rm -rf`.

## Switch to separate DB users

One-time, manual step done by the owner, after the release that adds `DB_MIGRATE_USER` /
`DB_MIGRATE_PASSWORD` (#109) is live. The grants of each user are in
[server.md](server.md#database-users).

Edit the shared `.env` on the host (`$REMOTE_HOME/$SHARED_DIR/.env`), in this order:

1. Add `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` with the **current** `DB_USER` /
   `DB_PASSWORD` values (the user with DDL rights). Check the file:
   `cd <live> && $PHP_BIN bin/check-config`, then `$PHP_BIN bin/migrate --status`.
2. Switch `DB_USER` / `DB_PASSWORD` to the data-only app user. Check the file again with
   `bin/check-config`, then check that `https://<domain>/health.json` returns `200` and
   `https://<domain>/admin/` loads.
3. On the next deploy, check that the `release` job's `bin/migrate` succeeds and that
   `cd <live> && $PHP_BIN bin/migrate --status` lists no pending migration.

The order matters: if `DB_USER` is switched before `DB_MIGRATE_*` exists, `bin/migrate` falls
back to the data-only user and the next deploy's migrations fail.

To roll back, set `DB_USER` / `DB_PASSWORD` back to the migration user's values; no deploy is
needed, since `public/index.php` loads `config/.env` on every request.

## Rotate the deploy key

1. Generate a new ed25519 key: `ssh-keygen -t ed25519 -N '' -f <new key>`.
2. Add the public half to the host user's `~/.ssh/authorized_keys` with `restrict`:
   `restrict ssh-ed25519 AAAA... <comment>`.
3. Update `SSH_PRIVATE_KEY` in the `pidgey-deploy` context with the new private key, in the
   one-line format (see [variables.md](variables.md#ssh_private_key-format)).
4. Deploy (push a new tag or rerun the last release) to confirm the new key works.
5. Remove the old key's line from `authorized_keys`.

## Rotate the host key

If the host's SSH key changes, the context jobs fail on host key verification. Re-run
`ssh-keyscan -t ed25519`, check the fingerprint against the host's own and update
`SSH_KNOWN_HOSTS` (see [variables.md](variables.md#ssh_known_hosts-format)).

## Rotate `SECRET_KEY`

1. Generate a new key: `openssl rand -hex 32` (at least 32 bytes, no `,`).
2. Edit the shared `.env` on the host (`$REMOTE_HOME/$SHARED_DIR/.env`):
   - **routine rotation:** move the current `SECRET_KEY` into `PREVIOUS_SECRET_KEYS`, then set
     the new `SECRET_KEY`; remove the old one from `PREVIOUS_SECRET_KEYS` after at least
     `ACCESS_TOKEN_TTL`;
   - **leaked key:** set the new `SECRET_KEY` and drop the leaked key at once, without moving
     it to `PREVIOUS_SECRET_KEYS`.
3. Check the file: `cd <live> && $PHP_BIN bin/check-config`.

No deploy is needed: `public/index.php` loads `config/.env` on every request, so the change
takes effect on the next request.

What it invalidates (see
[api/authentication/access-token.md](../specs/api/authentication/access-token.md#key-rotation)
and [architecture/configuration.md](../specs/architecture/configuration.md#secret-keys)): only
access tokens signed with a key that is no longer `SECRET_KEY` or in `PREVIOUS_SECRET_KEYS`.
Clients get a `401` and refresh; refresh tokens are not signed with the key, so nobody is
logged out unless their refresh token has also expired. Today no endpoint reads `SECRET_KEY`
yet, so a rotation has no visible effect until authentication ships.
