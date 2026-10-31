# Production Server

Purpose: define the production host's folder layout, web directory, configuration file and
runtime requirements, and what must be confirmed on the host before the first release.

The release flow that fills these folders is in [release.md](release.md); the security rules
for them are in [security.md](security.md).

## Never committed

Real host names, SSH users, home paths, folder paths and domains are **never** committed —
not in specs, docs, CI config or code. The repository refers to them only through CircleCI
variable names (`SSH_HOST`, `SSH_USER`, `SSH_REMOTE_DIR`, `SSH_REMOTE_TEMP_DIR`,
`REMOTE_HOME`, `PHP_BIN`, see [release.md](release.md#circleci-variables)) or obvious
placeholders such as `<shared folder>` and `<live>`.

## Folders

| Folder | Location | Content | Lifetime |
| --- | --- | --- | --- |
| Live | `$SSH_REMOTE_DIR` | The current release root (the content of `source/`, with `vendor/` and the built `public/admin/`). | Replaced on every release by the swap. |
| Staging | `${SSH_REMOTE_TEMP_DIR}-<workflow workspace id>` | The release being prepared by one workflow. | Created by the first upload; renamed to live by `release`; stale ones removed by `cleanup`. |
| Shared | `$REMOTE_HOME/<shared folder>` | The production `.env`. | Persistent, outside every release; created by hand by the owner before the first release. |

- Staging and live are on the same filesystem, so the swap is a plain rename.
- The live folder exists before the first deploy, so the first release needs no special case.
- There are no other persistent folders for now. PHP output goes to the host's Apache logs,
  outside the release. A future persistent folder would live in the shared folder and be
  added to the release with another `link` step.

### Shared folder

- Mode `700`; the production `.env` inside it is mode `600`. Both are owned by the host user,
  which is the user PHP runs as.
- The `.env` holds every app setting (`SECRET_KEY`, `DB_*`, `PHP_ENV`,
  `USER_APPROVAL_REQUIRED`, …) in the format defined in
  [architecture/configuration.md](../architecture/configuration.md#env-file). It is the only
  place these settings exist; they are never CircleCI variables.

### The `config/.env` link

Each release gets a symlink `config/.env` → `$REMOTE_HOME/<shared folder>/.env`, created in
staging by the `release` job before `check-config` and `migrate` run. `config/` is outside
`public/`, so the file is never web-reachable. The app's env-file loader reads it from the
release root (see [architecture/configuration.md](../architecture/configuration.md#env-file)).

## Web directory

- Pidgey runs at the root of its own (sub)domain, with no base path.
- The domain's web directory points at the **live release's `public/`** folder, so `bin/`,
  `config/`, `lib/`, `migrations/` and `vendor/` are not web-reachable.
- The admin UI is served from `public/admin/` at `/admin/`, same origin as the API.
- The host already redirects HTTP to HTTPS.

## Host requirements

- **PHP 8.4 (8.4.x)** for both the web (set in the hosting panel) and the CLI.
  - The CLI is called through `PHP_BIN`, the absolute path of the PHP 8.4 binary. Scripts on
    the host never call a bare `php`, which may be another version.
  - The CLI needs `pdo_mysql` and `sodium`.
  - `composer.json`'s `^8.4` still allows newer versions in development, but production is
    pinned to 8.4.x. `bin/check-config` fails the deploy when the running PHP is not 8.4.x.
- **Apache** with `.htaccess` support and `mod_rewrite` (see
  [request-handler/rewrite.md](../request-handler/rewrite.md)).
- **OPcache** with `opcache.validate_timestamps` on (the default): release paths do not
  change on a swap, so PHP must notice the new files.
- **MySQL 8+**, on its own hostname, reachable from the SSH host (migrations run there over
  SSH). The DB user needs `CREATE`, `ALTER`, `INDEX` and `REFERENCES` in addition to data
  access; `GET_LOCK` needs no privilege.
- **No Composer** is needed on the host: `vendor/` ships with the release.

## To confirm

Operational checks done on the host before the first release (#100). They are not spec
decisions.

- [ ] The web PHP version for the domain is 8.4 (it cannot be checked from the CLI, so
  `bin/check-config` does not cover it).
- [ ] The domain's web directory points at the live release's `public/`.
- [ ] The MySQL server version (`SELECT VERSION()`) is 8 or newer.
- [ ] The DB user's grants (`SHOW GRANTS`) include the rights listed above.
- [ ] OPcache has `validate_timestamps` on.
- [ ] The final domain.

## Pending decisions

None.
