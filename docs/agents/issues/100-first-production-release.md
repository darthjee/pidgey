# Issue: First production release

## Description

Part of epic #95. Prepare the host, CircleCI and GitHub settings, then make and check the first production release. The deploy pipeline (#99), the env-file loader and `bin/check-config` (#97) and the CI images (#98) are merged. This issue is mostly manual: the owner does the steps in the hosting panel, on the server, and in CircleCI and GitHub settings. Infra helps and writes down anything that differs from the specs in `docs/agents/specs/deployment/`.

## Expected Behavior

Pushing a semver tag (`X.Y.Z`, no `v` prefix) on a green commit deploys Pidgey to production through the `pidgey-deploy` workflow. The live release serves the API over HTTPS and the admin UI at `/admin/`, its migrations are applied, and only `public/` is reachable from the web.

## Solution

### Access and secrets
- [x] A dedicated ed25519 deploy key, authorized only for Pidgey's host user, with `restrict` in `authorized_keys`.
- [x] `SSH_KNOWN_HOSTS` (the ED25519 line from `ssh-keyscan`), with the fingerprint checked against the host.
- [x] A CircleCI context `pidgey-deploy` with every deploy variable: `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`, `SSH_REMOTE_DIR`, `SSH_REMOTE_TEMP_DIR`, `REMOTE_HOME`, `SHARED_DIR` and `PHP_BIN`. The deploy variables are removed from the project-level environment variables.
- [x] Context restricted to semver-tag pipelines.
- [x] "Pass secrets to builds from forked pull requests" is off.

### Host setup
- [x] The shared folder `$REMOTE_HOME/$SHARED_DIR` (`700`) and its `.env` (`600`), with the production settings.
- [x] The domain's web directory points at the live release's `public/`. It was set before the first tag, using an empty `public/` folder created by hand.

### Host checks marked *to confirm* in `server.md`
- [x] PHP 8.4 for the web and for `PHP_BIN`.
- [x] No web directory of the hosting account contains the live, staging, `_old` or shared folders.
- [x] No reverse proxy in front of Apache (`TRUSTED_PROXY_HOPS=0`).
- [x] MySQL 8.4.
- [x] The DB user has `CREATE`, `ALTER`, `INDEX` and `REFERENCES` on Pidgey's database only.
- [x] The final domain is chosen.

### Release
- [x] Pushed tag `0.1.0`, and the workflow passed.
- [x] Verified the release:
  - [x] the API answers over HTTPS, and HTTP redirects to HTTPS;
  - [x] `/admin/` loads;
  - [x] all migrations are applied (`bin/migrate --status`);
  - [x] `bin/`, `vendor/` and `config/` are not reachable from the web;
  - [x] no staging or `_old` folders are left after the swap.

### Findings for the implementation docs (#101)
- `SSH_PRIVATE_KEY` is stored as one line, with each newline escaped as a literal `\n`.
- `SSH_KNOWN_HOSTS` holds a single ED25519 line, which avoids multi-line values.
- Version `0.11.0` of the deploy script has no `cleanup` action, so after a failed deploy, stale staging folders are removed by hand.
- Every path currently returns `200 {"status":"ok"}`, because the status handler is the only handler. Unknown routes should get a 404 in a separate issue.
- `TRUSTED_PROXY_HOPS=0` can't be checked from outside until an endpoint shows the client IP.
