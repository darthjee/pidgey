# Issue: Specs: production deployment

## Description

Part of epic #95 (deploy Pidgey to production); the epic body holds the full reasoning behind every decision summarized here. Write the production deployment specs as topic files under `docs/agents/specs/deployment/` (indexed by `docs/agents/specs/deployment.md`). Also write every spec change outside that folder that the implementation sub-issues #97–#99 need, so they start from a merged spec.

## Problem

The deployment specs only describe CI checks. The deploy jobs are "future work", and the hosting target, frontend delivery, migrations on deploy and the production image are pending decisions. Without specs, #97 (env-file loader and `bin/check-config`), #98 (CI images) and #99 (deploy workflow) have no contract to implement against.

## Expected Behavior

### File layout

Follows the spec granularity rule: `deployment.md` is the index and the detail files live in `deployment/`. Each file opens with a title and a purpose line, and ends with **Pending decisions**.

| File | Status | Content |
| --- | --- | --- |
| `deployment.md` | update | Index, one row per file below. |
| `deployment/pipeline.md` | extend | CircleCI jobs and workflow. <br>• The future-work `upload_fe_files` / `release` rows become the real deploy jobs: semver tag filters, the `pidgey-deploy` context, the `serial-group`, job order, and each job's image. <br>• Settles the hosting and migrations-on-deploy pending decisions. <br>• `build_images` stays future work. |
| `deployment/release.md` | new | Step-by-step release flow: <br>• the per-workflow staging folder; <br>• backend build (`composer install --no-dev`), upload and exclude list; <br>• frontend build and upload to `public/admin/`; <br>• the `.env` link, `check-config`, `migrate`, `release` and `cleanup` steps; <br>• the `deploy_frontend.sh` actions and script version used; <br>• the CircleCI variable table. |
| `deployment/server.md` | new | Server side: <br>• live, staging and shared folders, with permissions; <br>• the `config/.env` link; <br>• the web directory pointing at `public/`; <br>• host requirements: `PHP_BIN` (PHP 8.4), Apache with `mod_rewrite`, OPcache `validate_timestamps`, MySQL 8+; <br>• the *to confirm* checks; <br>• the rule that real hosts, users, paths and domains are never committed. |
| `deployment/security.md` | new | The security decisions from #95: <br>• known hosts, the deploy key, the CI context and the tag ruleset; <br>• `.env` permissions, what ships, and no secrets in logs; <br>• one DB user for v1 (pending); <br>• the proxy rate limit as a known gap. |
| `deployment/edge-cases.md` | new | Failure modes and how each is handled: <br>• leftover staging, reruns and concurrent tags; <br>• migrations ahead of live code, a failed migration, and a broken swap (with manual recovery); <br>• requests during the swap and OPcache; <br>• a missing `.env` and a stale admin `index.html`. |
| `deployment/production.md` | keep, update | Production recommendations the app doesn't enforce. The proxy rate limit stays, noting that the host can't apply it and linking to `security.md`. |

### Specs outside `deployment/`

This issue also writes every spec change that sub-issues #97–#99 implement. That keeps the work spec-first: each implementation issue starts from a merged spec, the shared contract (`.env` location, `check-config`, image tooling) is fixed once, and the `security` agent reviews it in one pass.

| Spec | Change |
| --- | --- |
| `architecture/configuration.md` | **Env-file loader:** <br>• reads `config/.env` relative to the app root (`source/` in the repo, the release root on the server); <br>• format is `KEY=VALUE` lines: `#` comments and blank lines are ignored, there is no variable expansion, and optional surrounding quotes are stripped; <br>• never overrides an already-set variable, and is skipped silently when the file is missing; <br>• runs before anything reads `Env`, from `public/index.php` and every `bin/*`. <br>**`bin/check-config`:** <br>• runs the boot-time checks plus a PHP 8.4.x check; <br>• exit codes; <br>• output names the failed check, never a value. <br>`config/.env` is git-ignored. |
| `architecture/database.md` | The migration compatibility rule (expand first, contract in a later release), and that the deploy runs `bin/migrate` before the swap (link to `deployment/release.md`). |
| `architecture/repository-layout.md` | `source/config/`, which holds the linked `.env` in production (git-ignored). The backend upload exclude list lives at `.circleci/backend-exclude.txt` (owned by infra, so no new root folder). |
| `frontend/build.md` | Delivery settled as option C. `public/admin/.htaccess` only adds the no-cache header for `index.html`. |
| `docker/images.md` | Remove the `pidgey` production image. Specify the deploy tooling: `deploy_frontend.sh` from `darthjee/scripts` (with version), `ssh` and `rsync` in `circleci_pidgey-base`, and the frontend deploy image (extended or a new `dockerfiles/` image). |
| `docker/makefile.md` | Build and push targets for new or changed deploy images. |
| `testing/backend.md` | Per the testing strategy. |
| `docs/agents/contributing.md` | Its jobs table marks the deploy jobs *(planned)*. Update it to the real job names, still *(planned)* until #99 lands. |
| Inbound links (`specs.md`, `request-handler/rewrite.md`, `api/pending.md`, `api/endpoints/auth/session.md`, `api/endpoints/operational.md`, `frontend/testing.md`) | Point them at the files the content moved to (e.g. the rate-limit recommendation now links `deployment/security.md`), and update the `specs.md` index row. |

### Pending decisions

**Settled by this issue** (removed from "Pending decisions" and written into the specs):

| Spec | Pending today | Decision |
| --- | --- | --- |
| `deployment/pipeline.md` | Production hosting target | An SSH/rsync host, with connection details in CircleCI variables (the `pidgey-deploy` context). |
| `deployment/pipeline.md` | How migrations run on deploy | `$PHP_BIN bin/migrate` in the staging folder, after `check-config` and before the swap. |
| `deployment/pipeline.md` | Image tagging scheme | Dropped: no production image is built. A release is identified by its git semver tag. |
| `deployment/pipeline.md`, `frontend/build.md` | How the built frontend reaches production | Option C: the deploy workflow builds it and uploads it into `public/admin/`. Options A and B are recorded as rejected, with the reasons. |
| `frontend/build.md` | `/admin/` serving beyond `DirectoryIndex` | `DirectoryIndex` is enough. `public/admin/.htaccess` exists only to send `Cache-Control: no-cache` for `index.html`. |
| `docker/images.md` | The `pidgey` production image (future work) | Removed: the rsync deploy replaces it, and production runs from uploaded files. |
| `deployment/server.md` | Production PHP version | **PHP 8.4** (8.4.x), for both the web (set in the hosting panel) and the CLI (`PHP_BIN`). `composer.json`'s `^8.4` still allows newer versions for development, but production is pinned. `bin/check-config` fails the deploy when the running PHP is not 8.4.x, which catches a wrong `PHP_BIN` or a host change before the swap. The web version can't be checked from the CLI, so it stays a manual check before the first release (#100). |

**Recorded as pending** (stay open, stated in the specs):

| Spec | Pending decision |
| --- | --- |
| `deployment/security.md` | A separate least-privilege DB user for migrations (v1 uses one user). |
| `deployment/security.md` | An HSTS header. |
| `deployment/security.md`, `deployment/production.md` | Auth rate limiting, which the host can't do at the proxy. Links to the app-level throttling item in `api/pending.md`. |

**Untouched** (pending decisions not about deployment): frontend coverage to Codacy (`pipeline.md`) and the log-level variable (`configuration.md`).

**Operational checks, not spec decisions:** listed as *to confirm* in `server.md` and done in #100:
- the web PHP version and the web directory;
- the MySQL version and the DB user's grants;
- OPcache;
- the final domain.

## Solution

### What the specs say

- **Flow:**
  - a semver tag (`\d+\.\d+\.\d+`, no `v` prefix) triggers deploy jobs, after `backend_checks`, `backend_tests` and `frontend_checks` pass;
  - every job fills one staging folder (`${SSH_REMOTE_TEMP_DIR}-${CIRCLE_WORKFLOW_WORKSPACE_ID}`): backend upload, frontend upload into `public/admin/`, a symlink to the shared `.env` at `config/.env`, `bin/check-config`, then `$PHP_BIN bin/migrate`, both run in staging;
  - `release` swaps staging into the live folder (no rollback copy), then `cleanup` removes stale staging folders.
- **CI jobs and deploy script:**
  - the shared `deploy_frontend.sh` from `darthjee/scripts` (`0.11.0`, and `0.12.0` for `cleanup`), and the actions each job uses;
  - CircleCI variables `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`, `SSH_REMOTE_DIR`, `SSH_REMOTE_TEMP_DIR`, `REMOTE_HOME` and `PHP_BIN`, in a `pidgey-deploy` context;
  - a `serial-group` so deploys run one at a time;
  - the upload exclude list.
- **Server layout and configuration:**
  - the web directory points at the release's `public/`;
  - a shared folder outside releases holds `.env` (`700`/`600`).
  - App settings live only there; they are never CircleCI variables.
  - Concrete hosts, users, paths and domains are never committed.
- **Security:**
  - required `SSH_KNOWN_HOSTS`;
  - a dedicated `restrict`ed deploy key;
  - a deploy-only context, with forked-PR secrets off;
  - a GitHub tag ruleset;
  - no secrets in logs;
  - one DB user for v1 (pending a least-privilege migration user);
  - proxy rate limiting is not available on the host (a known gap; keep `USER_APPROVAL_REQUIRED=true`).
- **Edge cases:**
  - leftover staging folders, reruns and concurrent tags;
  - migrations ahead of live code, a failed migration, and a broken swap (manual recovery);
  - OPcache `validate_timestamps`;
  - a missing `.env`;
  - a stale admin `index.html`, served with `Cache-Control: no-cache`.

### Testing strategy

Written into the specs as follows. It refines the edge cases and does not add a new file:
- **`testing/backend.md`:** the env-file loader gets unit tests for parsing (comments, blank lines, quotes, no expansion), for never overriding a variable that is already set, and for a missing file. `bin/check-config` gets integration tests that run it with valid and invalid configurations and with a wrong PHP version, checking exit codes and that no value is ever printed.
- **`deployment/release.md`:** there is no automated test of the deploy workflow itself, because it only runs against the real host. It is verified by:
  - the `darthjee/scripts` test suite (script behaviour);
  - the pre-swap guards: `SSH_KNOWN_HOSTS` set, `check-config`, `migrate`, and the `release` staging check;
  - a post-release smoke check, which is the manual first-release checklist in #100: HTTPS API answers, `/admin/` loads, `bin/migrate --status` is clean, and `bin/`, `vendor/` and `config/` are not web-reachable.

### Backward compatibility

- The env-file loader is a no-op when `config/.env` is missing and never overrides a variable that is already set. Docker compose, CI and tests behave exactly as today.
- No API, schema or runtime behaviour changes. Removing the `pidgey` production image removes only something that was future work and never built.

### Out of scope

- Implementing any of it (#97–#99), and the first release (#100).

### Notes

- Requires a `security` agent review before merge.
- If a spec needs more work than fits here, open a follow-up issue linked to #95.

## Benefits

One reviewed spec fixes the deploy contract: the `.env` location and format, `check-config`, the image tooling and the job flow. The implementation issues can then proceed independently, and the deploy security decisions get a single `security` review.
