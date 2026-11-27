# Operations

Runbooks for releasing Pidgey, for the manual tasks the deploy does not automate, and for
publishing the client packages to npm (from [Set up npm publishing](#set-up-npm-publishing)
on).

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
   - `https://<domain>/` loads;
   - an unknown extensionless path redirects into the UI:
     `curl -sI https://<domain>/does-not-exist` shows `302` and
     `Location: /#/does-not-exist`;
   - the UI assets are compressed:
     `curl -sI -H 'Accept-Encoding: gzip' https://<domain>/assets/<bundle>.js` (a bundle name
     taken from the built `index.html`) shows `Content-Encoding: gzip`. If it does not, record
     it in [known-gaps.md](known-gaps.md);
   - the HSTS header is sent: `curl -sI https://<domain>/health.json` and
     `curl -sI https://<domain>/` both show `Strict-Transport-Security: max-age=86400`;
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
[architecture/database.md](../architecture/database.md)).

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
[architecture/database.md](../architecture/database.md#compatibility-with-the-previous-release)).

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
   `https://<domain>/` loads.
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
[api/authentication/access-token.md](../api/authentication/access-token.md#key-rotation)
and [architecture/configuration.md](../architecture/configuration.md#secret-keys)): only
access tokens signed with a key that is no longer `SECRET_KEY` or in `PREVIOUS_SECRET_KEYS`.
Clients get a `401` and refresh; refresh tokens are not signed with the key, so nobody is
logged out unless their refresh token has also expired. Today no endpoint reads `SECRET_KEY`
yet, so a rotation has no visible effect until authentication ships.

## Rotate `SMTP_ENCRYPTION_KEY`

Stored SMTP passwords are encrypted with `SMTP_ENCRYPTION_KEY`; keys in
`PREVIOUS_SMTP_ENCRYPTION_KEYS` are used only to decrypt (see
[email/password-encryption.md](../email/password-encryption.md)).

1. Generate a new key: `openssl rand -hex 32` (at least 32 bytes, no `,`, different from
   `SECRET_KEY`).
2. Edit the shared `.env` on the host (`$REMOTE_HOME/$SHARED_DIR/.env`): move the current
   `SMTP_ENCRYPTION_KEY` into `PREVIOUS_SMTP_ENCRYPTION_KEYS`, then set the new
   `SMTP_ENCRYPTION_KEY`.
3. Check the file: `cd <live> && $PHP_BIN bin/check-config`.
4. Re-encrypt: `$PHP_BIN bin/reencrypt-smtp-passwords`, then run
   `$PHP_BIN bin/reencrypt-smtp-passwords --check` until it reports no row under an old key and
   no unreadable row.
5. Remove the old key from `PREVIOUS_SMTP_ENCRYPTION_KEYS`.

- **Leaked key:** same steps, but remove the leaked key right after step 4 instead of keeping
  it around.
- No deploy is needed: `public/index.php` loads `config/.env` on every request. The deploy
  never runs `bin/reencrypt-smtp-passwords` itself.
- Removing an old key before step 4 has finished leaves its rows unreadable: the test email
  (and later sends) answer `409 SMTP_PASSWORD_UNREADABLE` until the key is restored as a
  previous key or the password is set again. Back up every key separately from the database
  (see [server.md](server.md#shared-env)).

## Add `SMTP_ENCRYPTION_KEY` before the first release that validates it

`bin/check-config` refuses a missing `SMTP_ENCRYPTION_KEY` once the encryption ships, and the
release pipeline runs it before the swap. Add the key to the shared `.env` (step 1 and 2 of
the rotation above, with no previous key) **before** deploying that release, otherwise the
release stops before going live. No SMTP rows exist in production yet, so nothing needs
migrating.

## Set up npm publishing

Manual, done once by the maintainer, **before** pushing the first client release tag. None of
it is in the repository, and no real publish has run yet (see
[known-gaps.md](known-gaps.md#npm-publishing-not-set-up-or-exercised-yet)).

1. Enable 2FA on the npm account.
2. Make sure `pidgey-client` and `pidgey-cli` exist on npm (the `0.0.0` placeholders, #227):
   the token is limited to those two packages.
3. In each package's **Settings** → **Publishing access**, require 2FA while still allowing a
   granular access token that bypasses it. Do **not** select
   "Require two-factor authentication and disallow tokens": it blocks token publishing.
4. Create the granular access token with the settings in
   [variables.md](variables.md#npm-context).
5. Create the CircleCI context `pidgey-npm` holding only `NPM_TOKEN`, with the project
   restriction and the expression restriction in
   [pipeline.md](pipeline.md#context-restriction), **before** storing the token in it.
6. Check that "Pass secrets to builds from forked pull requests" is off, and that no
   `NPM_TOKEN` project variable exists.
7. Set a reminder before the token expires.

## Release a client package

Direct publishing with this token ends in January 2027 (see
[known-gaps.md](known-gaps.md#npm-token-publishing-ends-in-january-2027)).

1. **Release PR:** bump `version` in the package's `package.json` (`clients/nodejs/` or
   `clients/cli/`) and add its `CHANGELOG.md` entry, a `## X.Y.Z` heading (optionally
   `## X.Y.Z - YYYY-MM-DD`). A release of both packages bumps both in the same PR.
2. After the merge, check that CI is green on `main`, then tag the merge commit and push the
   tag. When releasing both, push **only the library tag** first:

   ```bash
   git tag pidgey-client-vX.Y.Z <commit>
   git push origin pidgey-client-vX.Y.Z
   ```

3. Watch the `main` workflow for the tag: the check jobs, `pack_client`, then
   `publish_client` (see [pipeline.md](pipeline.md#npm-publish-chain)).
4. `npm view pidgey-client@X.Y.Z version` prints the version.
5. **Only after `publish_client` succeeded**, tag and push the CLI, if it is released too:

   ```bash
   git tag pidgey-cli-vX.Y.Z <commit>
   git push origin pidgey-cli-vX.Y.Z
   ```

   Pushing both tags at once can skip the library publish (see
   [known-gaps.md](known-gaps.md#releasing-both-clients-at-once)).
6. Check `pack_cli`, `publish_cli` and `npm view pidgey-cli@X.Y.Z version`.

A CLI-only release needs only its own tag: the library version it depends on is already on
npm.

## Dry-run a client release locally

Runs the gates, the pack, the byte comparison and `npm publish --dry-run`. Nothing is
published and no token is needed. Use Node.js 22.12 or later, with Yarn and npm, from the
repository root. The tag does not need to exist in git, but its version must equal the one in
`package.json`, and `CHANGELOG.md` must have that entry; to try it before the release PR, add
the heading locally and do not commit it.

```bash
out="$(mktemp -d)"
clients/scripts/release_pack.sh pidgey-client-vX.Y.Z "$out"
clients/scripts/release_publish.sh --dry-run pidgey-client-vX.Y.Z "$out/pidgey-client-X.Y.Z.tgz"
```

For the CLI, use `pidgey-cli-vX.Y.Z` and `pidgey-cli-X.Y.Z.tgz`. `--dry-run` must be the
first argument. When the version is already on npm, the publish script ends as a no-op; when
the library floor of the CLI is not on npm, it only warns.

## Rerun a failed publish

Use **Rerun from failed** in CircleCI. A publish is safe to repeat: when npm already serves
the version, the job succeeds without publishing.

| Failure | Action |
| --- | --- |
| A gate of the pack job (tag, version, `CHANGELOG.md`, file list) | Fix it in a new commit on `main`. If the version was never published, delete the tag and push it on the new commit, or release the next patch version. |
| The publish job reports that the tarball differs from the checkout | Nothing was published. Rerun the **whole** workflow, so the tarball is packed again. If it differs again, treat the pack job as suspect (a dependency changing files) and investigate before retrying. |
| The CLI gave up waiting for the library floor | Publish the library first, then rerun the CLI workflow. |
| A publish job shows as skipped | The npm serial group was busy with a newer pipeline. Rerun the workflow once no publish job is running or waiting. |
| npm did not serve the new version within 5 minutes | Rerun: the job succeeds once npm answers. |
| `401` or `403` from npm | Check the token: see [Rotate the npm token](#rotate-the-npm-token). |

## Rotate the npm token

1. Create a new granular token with the same settings
   (see [variables.md](variables.md#npm-context)).
2. Replace `NPM_TOKEN` in the `pidgey-npm` context.
3. Revoke the old token on npm.
4. Set a reminder before the next expiry.

An expired or revoked token shows up as a `401` or `403` in the publish job: rotate, then
rerun the job. From January 2027 a refused publish may instead come from npm's removal of
direct publishing, which rotating does not fix (see
[known-gaps.md](known-gaps.md#npm-token-publishing-ends-in-january-2027)). For a **leaked**
token, revoke it first, then check the published versions of both packages.

## Deprecate a client release

A bad release is deprecated and fixed forward, not unpublished. From the maintainer's machine
with 2FA, never from CI:

```bash
npm deprecate <package>@<version> "<reason>; use <fixed version>"
```

Then [release](#release-a-client-package) a patch version, which also moves the `latest`
dist-tag. A fixed library patch reaches every CLI install without a CLI release.

After the first real release (`0.1.0`) of a package, deprecate its placeholder the same way:
`npm deprecate <package>@0.0.0 "placeholder; use >=0.1.0"`.

## Unpublish a client release

Only to remove a leaked secret, within npm's 72-hour window, from the maintainer's machine:
`npm unpublish <package>@<version>`. **Rotate the leaked secret too**; unpublishing does not
undo copies already downloaded. Then release a new version: npm does not accept a version
number twice.
