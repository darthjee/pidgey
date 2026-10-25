# Make infra.md accurate
Make `.claude/agents/infra.md` match the repo. Change only what is inaccurate and keep the rest of the file as it is.

- `dockerfiles/`: `dev_pidgey-base/`, `dev_pidgey/`, `vite_pidgey-base/`, `vite_pidgey/`, `circleci_pidgey-base/`, `mysql/`. Remove `pidgey/` (production): it does not exist yet, so it is future work and not part of the current scope.
- `docker-compose.yml`: check the service list against the file and correct it if needed.
- `Makefile`: list the targets that exist (`build-base`, `push-base`, `build-ci-base`, `push-ci-base`, `build`, `build-fe-base`, `push-fe-base`, `build-fe`, `fe-install`, `build-fe-release`, `dev`, `dev-up`, `tests`, `migrate`, `db-reset`). Re-check them against `Makefile`.
- `.circleci/`: `backend_checks`, `backend_tests` and `frontend_checks` exist. `build_images`, `upload_fe_files` and `release` are future work; production delivery is still a Pending decision in `docs/agents/specs/frontend/build.md`.
- Stack line: add Node.js/Yarn only through the `vite_pidgey*` images.
- Spec pointers: `docs/agents/specs/docker.md` and `docs/agents/specs/deployment.md` are now index files, so point to them as indexes of `docker/*.md` and `deployment/*.md`.
- Commands: add `docker compose config -q`, `make build` and `make build-fe` (the latter only when frontend images change).

## Files to Change
- `.claude/agents/infra.md` — accuracy fixes only.
