# Deployment folder

Write or update every file in `docs/agents/specs/deployment/`, plus the `deployment.md` index.
The content comes from the issue sections "What the specs say", "Pending decisions" and
"Testing strategy".

- **`deployment.md`**: the index, with one row per file below.
- **`deployment/pipeline.md`** (extend):
  - Replace the future-work `upload_fe_files` / `release` rows with the deploy jobs:
    - a backend upload job (PHP 8.4 image `circleci_pidgey-base`, `composer install --no-dev`);
    - a frontend upload job (node image with the script);
    - a release job: `link`, `check-config`, `migrate`, `release`, `cleanup`.
  - Every deploy job:
    - is filtered to semver tags `/^\d+\.\d+\.\d+$/` with branches ignored;
    - requires `backend_checks`, `backend_tests` and `frontend_checks`;
    - uses the `pidgey-deploy` context and one `serial-group`.
  - Update "Workflow" (checks on every branch and tag; deploy jobs only on semver tags).
  - Pending decisions:
    - settle hosting, migrations on deploy and the image tagging scheme (dropped);
    - point frontend delivery at option C;
    - `build_images` stays future work;
    - the frontend-coverage-to-Codacy item stays.
- **`deployment/release.md`** (new): the step-by-step flow.
  - Staging folder `${SSH_REMOTE_TEMP_DIR}-${CIRCLE_WORKFLOW_WORKSPACE_ID}`.
  - Backend build and upload to the staging root, with the exclude list at
    `.circleci/backend-exclude.txt`: `tests/`, `coverage/`, lint and phpdoc config,
    `phpunit.xml`, `.env*`, `.git`.
  - Frontend `vite build`, uploaded with `DEPLOY_PATH=public/admin/`.
  - In the release job:
    - `link` of the shared `.env` to `config/.env`;
    - `run` of `$PHP_BIN bin/check-config`;
    - `run` of `$PHP_BIN bin/migrate`;
    - `release`;
    - `cleanup`, from `0.12.0`.
  - The `deploy_frontend.sh` actions used, and the script version.
  - Table of CircleCI variables (`SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`,
    `SSH_KNOWN_HOSTS`, `SSH_REMOTE_DIR`, `SSH_REMOTE_TEMP_DIR`, `REMOTE_HOME`, `PHP_BIN`): name,
    meaning, required, and secret or not.
  - App settings are never CircleCI variables.
  - Verification part of the testing strategy:
    - no automated test of the workflow itself;
    - the script's own test suite;
    - the pre-swap guards;
    - the post-release smoke check (#100).
- **`deployment/server.md`** (new):
  - live, staging and shared folders, with permissions (shared folder `700`, `.env` `600`);
  - the `config/.env` symlink;
  - the web directory pointing at the live release's `public/`.
  - Host requirements:
    - PHP **8.4.x** for both the web and the CLI (`PHP_BIN`); never a bare `php`;
    - Apache with `.htaccess` and `mod_rewrite`;
    - OPcache with `validate_timestamps` on;
    - MySQL 8+, reachable from the SSH host.
  - The *to confirm* list:
    - the web PHP version and the web directory;
    - the MySQL version and DB grants;
    - OPcache;
    - the final domain.
  - The rule that real hosts, users, paths and domains are never committed.
- **`deployment/security.md`** (new): the security decisions in the issue.
  - Required `SSH_KNOWN_HOSTS`, with the deploy jobs failing early when it's unset.
  - A dedicated ed25519 key with `restrict` and no `from=`.
  - The deploy-only context, with forked-PR secrets off.
  - A GitHub tag ruleset for semver tags.
  - `.env` permissions and location outside `public/`.
  - What ships.
  - No secrets in logs (`check-config` prints check names only).
  - Pending decisions:
    - a separate migration DB user;
    - HSTS;
    - auth rate limiting: a known gap on this host, linking `api/pending.md`; keep
      `USER_APPROVAL_REQUIRED=true` in production.
- **`deployment/edge-cases.md`** (new): every edge case from the issue, each with its handling.
  The broken-swap entry includes the manual recovery (`mv <live>_old_<ts> <live>`).
- **`deployment/production.md`** (update): keep the proxy rate-limit recommendation, add that
  the current host can't apply it, and link `security.md`.

## Files to Change
- `docs/agents/specs/deployment.md`
- `docs/agents/specs/deployment/pipeline.md`
- `docs/agents/specs/deployment/release.md` (new)
- `docs/agents/specs/deployment/server.md` (new)
- `docs/agents/specs/deployment/security.md` (new)
- `docs/agents/specs/deployment/edge-cases.md` (new)
- `docs/agents/specs/deployment/production.md`
