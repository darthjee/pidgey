# Setup docs

- `README.md`: add a short CI section — CircleCI runs `backend_checks`/`backend_tests` on every
  branch and tag; one-time setup: enable the project on CircleCI and Codacy, set
  `CODACY_PROJECT_TOKEN` in the CircleCI project env vars, build and push the CI image with
  `make build-ci-base && make push-ci-base` (bump `CI_BASE_VERSION` and the tag in
  `.circleci/config.yml` when `composer.lock` or the Dockerfile changes). Optionally add the
  CircleCI and Codacy badges.
- `docs/agents/folder-structure.md`: drop *(planned)* from `.circleci/` and
  `circleci_pidgey-base/`; add `.codacy.yml`; update the `Makefile` line (no longer "used by
  CI" via wrappers).
- `docs/agents/contributing.md`: remove the "Until `.circleci/config.yml` exists" fallback
  wording if it no longer applies.

## Files to Change
- `README.md` — CI/Codacy setup section.
- `docs/agents/folder-structure.md` — reflect new root files/folders.
- `docs/agents/contributing.md` — CI section now points at the real config.
