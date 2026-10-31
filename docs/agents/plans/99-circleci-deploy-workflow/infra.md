# Infra Plan: CircleCI deploy workflow

Main plan: [plan.md](plan.md)

## Shared contracts

- Context variables the context jobs read:
  - `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`;
  - `SSH_REMOTE_DIR`, `SSH_REMOTE_TEMP_DIR`;
  - `REMOTE_HOME`, `SHARED_DIR` (new), `PHP_BIN`.
- The frontend agent provides `frontend/public/.htaccess`. It ends up in `dist/`, and `upload_frontend` uploads it with no extra handling.
- `deploy_frontend.sh` 0.11.0 interface: see [plan.md](plan.md#shared-contracts).

## Steps

- [01 — Backend exclude list](infra/01-backend-exclude-list.md)
- [02 — Single CI image version and deploy commands](infra/02-image-and-commands.md)
- [03 — Deploy jobs and workflow](infra/03-deploy-jobs-and-workflow.md)

## CI Checks

- `.circleci/config.yml`: run `circleci config validate` if the CLI is available. Otherwise check the YAML syntax. The deploy chain can't run outside a real semver tag, so the check jobs (`backend_checks`, `backend_tests`, `frontend_checks`) on the PR branch are the only CI signal.

## Notes

- This change needs a `security` agent review before merge, covering:
  - context isolation (build jobs have no context);
  - the `SSH_KNOWN_HOSTS` guard;
  - the exclude list;
  - `COMMAND` quoting.
- No `cleanup` step: `darthjee/scripts:0.12.0` isn't released yet (#106).
- The owner sets up the CircleCI context, its semver-tag expression restriction and the `SHARED_DIR` variable by hand. That work is outside this change.
