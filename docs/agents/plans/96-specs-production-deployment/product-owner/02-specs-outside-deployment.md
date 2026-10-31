# Specs outside deployment/

Write the spec changes that #97–#99 implement, following the issue's
"Specs outside `deployment/`" table.

- **`architecture/configuration.md`**:
  - **Env-file loader:**
    - It reads `config/.env` relative to the app root.
    - Format: `KEY=VALUE` per line. `#` comment lines and blank lines are ignored, there is no
      variable expansion, and surrounding single or double quotes are stripped.
    - It never overrides a variable that is already set, and does nothing when the file is
      missing.
    - It runs before anything reads `Env`, from `public/index.php` and every `bin/*` script.
    - `config/.env` is git-ignored.
    - Docker, CI and tests are unchanged.
  - **`bin/check-config`:**
    - loads the env file, then runs the same validation as boot (`SECRET_KEY` and
      `PREVIOUS_SECRET_KEYS` rules, the required `DB_*`), plus a check that the running PHP
      is 8.4.x;
    - exits `0` when everything passes and non-zero otherwise;
    - output names each failed check, never a value.
  - Keep the log-level pending decision as it is.
- **`architecture/database.md`**: in Migrations, add:
  - the compatibility rule: every migration must keep working with the previous release's
    code (expand first, contract in a later release);
  - that production runs `bin/migrate` in the staging folder before the swap (link
    `../deployment/release.md`). Replace the "later, at deploy time" wording.
- **`architecture/repository-layout.md`**:
  - add `source/config/` (holds the linked `.env` in production; git-ignored);
  - add `.circleci/backend-exclude.txt` (the backend upload exclude list, owned by infra).
- **`frontend/build.md`**:
  - Settle "how the built frontend reaches production" as option C. Record A and B as
    rejected, with their reasons.
  - Settle "`/admin/` serving": `DirectoryIndex` is enough, and `public/admin/.htaccess`
    only sends `Cache-Control: no-cache` for `index.html`.
- **`docker/images.md`**:
  - Remove the `pidgey` production image row; production runs from uploaded files.
  - Specify the deploy tooling:
    - `circleci_pidgey-base` gains `deploy_frontend.sh`
      (`COPY --from=darthjee/scripts:<version> /home/scripts/sbin/deploy_frontend.sh /usr/local/sbin/`),
      `openssh-client` and `rsync`;
    - the frontend deploy job needs an image with node/yarn, the script, `ssh` and `rsync`.
      State the requirement, and leave the choice between extending an image and adding a
      new `dockerfiles/` image to #98.
  - Update the frontend-delivery references to point at the settled decision.
- **`docker/makefile.md`**: build and push targets for any new or changed deploy image,
  following the existing target naming.
- **`testing/backend.md`**: the loader's unit tests (parsing, no override, missing file) and
  `check-config`'s integration tests (valid, invalid, wrong PHP version; exit codes; no value
  in the output).

## Files to Change
- `docs/agents/specs/architecture/configuration.md`
- `docs/agents/specs/architecture/database.md`
- `docs/agents/specs/architecture/repository-layout.md`
- `docs/agents/specs/frontend/build.md`
- `docs/agents/specs/docker/images.md`
- `docs/agents/specs/docker/makefile.md`
- `docs/agents/specs/testing/backend.md`
