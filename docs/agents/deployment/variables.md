# Variables

List every CircleCI variable the deploy and the npm publish use, what it holds and its format.

## Where they live

- Every deploy variable lives **only** in the `pidgey-deploy` CircleCI context, and the npm
  token only in the `pidgey-npm` context (see [npm context](#npm-context)). None is set as a
  project variable, so jobs without a context (check jobs, build jobs) never see them.
- The context is attached only to the context jobs and restricted to semver-tag pipelines
  (see [pipeline.md](pipeline.md#context-restriction)).
- "Pass secrets to builds from forked pull requests" stays **off** in the project settings.
- Real values are never committed. The repository refers to them only by name, or by
  placeholders such as `<live>` and `<domain>`.

`CODACY_PROJECT_TOKEN` is the only project-level variable; it is used by the coverage upload,
not by the deploy or the npm publish.

## npm context

The `pidgey-npm` context holds one variable and is attached only to `publish_client` and
`publish_cli`, with the restrictions in
[pipeline.md](pipeline.md#context-restriction).

| Variable | Holds | Format | Used by |
| --- | --- | --- | --- |
| `NPM_TOKEN` | npm token that publishes both packages. **Secret.** | An npm granular access token, one line. | `release_publish.sh` (the publish command only). |

The token's settings, with the labels of the npm token form:

| Setting | Value |
| --- | --- |
| **Packages and scopes**: Permissions | **Read and write (publish and stage)**. |
| **Packages and scopes**: Select Packages | **Only select packages and scopes**: `pidgey-client` and `pidgey-cli`. |
| **Organizations** | No access. |
| **Bypass two-factor authentication** | Checked: CI cannot answer a 2FA prompt. Unchecked by default. |
| **Expiration** | A date, at most 90 days ahead. npm announced a 90-day maximum and a 7-day default for write tokens in [September 2025](https://github.blog/changelog/2025-09-29-strengthening-npm-security-important-changes-to-authentication-and-token-management/); the current token pages do not state either, so check the form. |

Labels and behaviour as read on 2026-11-27 in
[creating and viewing access tokens](https://docs.npmjs.com/creating-and-viewing-access-tokens)
and [about access tokens](https://docs.npmjs.com/about-access-tokens). The latter announces
that granular tokens lose direct publishing in **January 2027** (see
[known-gaps.md](known-gaps.md#npm-token-publishing-ends-in-january-2027)). Classic tokens no
longer exist.

`NPM_TOKEN` is never a project variable, and "Pass secrets to builds from forked pull
requests" stays off. Rotation steps are in
[operations.md](operations.md#rotate-the-npm-token).

## Deploy context variables

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

Why the context and not project variables: project variables reach every job of every
pipeline, including branch pipelines anyone with push access can start. The restricted context
reaches only the context jobs of semver-tag pipelines.

### Deploy key

`SSH_PRIVATE_KEY` is the private half of a **dedicated** ed25519 key, used only for Pidgey's
deploy and authorized only for Pidgey's own host user. Its `authorized_keys` entry starts with
`restrict` (no port, agent or X11 forwarding, no pty). Rotation steps are in
[operations.md](operations.md#rotate-the-deploy-key).

Why: a leaked key can then reach only Pidgey's host user, and only to run commands and copy
files. There is no `from=` restriction because CircleCI's IP addresses are not stable.

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

Why: `ssh-keyscan` trusts whatever answers on the network. Checking the fingerprint against the
host's own is what makes the pinned key, and `StrictHostKeyChecking=yes`, worth anything. The
value is not secret, but it is integrity-critical: replacing it would let a fake host receive
the deploy.

## App settings are not CircleCI variables

`SECRET_KEY`, `SMTP_ENCRYPTION_KEY`, `PREVIOUS_SMTP_ENCRYPTION_KEYS`, `DB_*`, `PHP_ENV`,
`USER_APPROVAL_REQUIRED`, `TRUSTED_PROXY_HOPS`, the SMTP transport keys
(`SMTP_CONNECT_TIMEOUT`, `SMTP_COMMAND_TIMEOUT`, `SMTP_TOTAL_TIMEOUT`, `SMTP_CA_FILE`,
`SMTP_EHLO_NAME`), the SMTP endpoint hardening keys (`SMTP_ALLOW_PRIVATE_HOSTS`,
`SMTP_TEST_MAX_ATTEMPTS`, `SMTP_TEST_WINDOW`), `LOG_LEVEL` and every
other setting the app reads live **only** in the shared `.env` on the host (see
[server.md](server.md#shared-env)). CI never sees them.
