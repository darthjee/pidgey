# Variables

Purpose: list every CircleCI variable the deploy uses, what it holds and its format.

## Where they live

- Every deploy variable lives **only** in the `pidgey-deploy` CircleCI context. None is set as
  a project variable, so jobs without the context (check jobs, build jobs) never see them.
- The context is attached only to the context jobs and restricted to semver-tag pipelines
  (see [pipeline.md](pipeline.md#context-restriction)).
- "Pass secrets to builds from forked pull requests" stays **off** in the project settings.
- Real values are never committed. The repository refers to them only by name, or by
  placeholders such as `<live>` and `<domain>`.

`CODACY_PROJECT_TOKEN` is the only project-level variable; it is used by the coverage upload,
not by the deploy.

## Context variables

| Variable | Holds | Format | Used by |
| --- | --- | --- | --- |
| `SSH_HOST` | Host name of the production SSH server. | Host name. | Every SSH/rsync call. |
| `SSH_PORT` | SSH port of that server. | Number. The script defaults to `22` when unset. | Every SSH/rsync call. |
| `SSH_USER` | Host user the deploy logs in as; the same user PHP runs as. | User name. | Every SSH/rsync call. |
| `SSH_PRIVATE_KEY` | Private half of the dedicated deploy key. **Secret.** | One line, with each newline escaped as a literal `\n` (see below). | `generate_key_file`. |
| `SSH_KNOWN_HOSTS` | The host's public key, pinned for `StrictHostKeyChecking=yes`. Required: `deploy_setup` fails without it. | A single ED25519 `known_hosts` line (see below). | `deploy_setup` guard, `generate_key_file`. |
| `SSH_REMOTE_DIR` | Live folder: the release root whose `public/` the web directory serves (`<live>`). | Absolute path, no trailing slash. | `release`. |
| `SSH_REMOTE_TEMP_DIR` | Prefix of the staging folders; the script appends `-${CIRCLE_WORKFLOW_WORKSPACE_ID}`. | Absolute path, no trailing slash. | `upload`, `link`, `run`, `release`. |
| `REMOTE_HOME` | Home folder of the host user, under which the shared folder lives. | Absolute path, no trailing slash. | `link` (shared `.env` path). |
| `SHARED_DIR` | Shared folder, relative to `REMOTE_HOME`. | Relative path. | `link` (shared `.env` path). |
| `PHP_BIN` | PHP 8.4 CLI on the host. Scripts on the host never call a bare `php`, which may be another version. | Absolute path of the binary. | `run` (`bin/check-config`, `bin/migrate`). |

All variables except `SSH_PRIVATE_KEY` are not credentials, but they describe the host and are
kept private all the same.

### `SSH_PRIVATE_KEY` format

The CircleCI field is stored as **one line**: each newline of the key file is replaced by the
two characters `\n`. `generate_key_file` turns them back into newlines when it writes the key
file. To produce the value from a key file and copy it to the clipboard (macOS):

```bash
awk 'NR>1{printf "\\n"}{printf "%s",$0}' <key> | pbcopy
```

### `SSH_KNOWN_HOSTS` format

A single ED25519 line, as printed by:

```bash
ssh-keyscan -t ed25519 -p <port> <host>
```

Before saving it, check its fingerprint (`ssh-keygen -lf <file>`) against the one the host
reports for its own key; a mismatch means the scan did not reach the real host.

## App settings are not CircleCI variables

`SECRET_KEY`, `DB_*`, `PHP_ENV`, `USER_APPROVAL_REQUIRED`, `TRUSTED_PROXY_HOPS` and every
other setting the app reads live **only** in the shared `.env` on the host (see
[server.md](server.md#shared-env)). CI never sees them.
