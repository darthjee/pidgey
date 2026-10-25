# Plan: Frontend agent: fix frontend.md, check_frontend.sh and commit author

Issue: [19-frontend-agent-fix-frontend-md-check-frontend-sh-and-commit-author.md](../../issues/19-frontend-agent-fix-frontend-md-check-frontend-sh-and-commit-author.md)

## Overview
Update the agent tooling under `.claude/` so it matches pidgey:
- rewrite the frontend agent definition, with citadel's conventions ported;
- fix the frontend and security check scripts;
- add per-agent commit authors;
- make `infra.md` accurate.

Everything is under `.claude/` (root-level tooling), so no specialist owns it and the architect implements it.

## Context
#16 (specs) and #17 (the `frontend_dev` service, `vite_pidgey*` images, Makefile targets) and #18 (CircleCI `frontend_checks`) are merged. `.claude/agents/frontend.md` and `.claude/scripts/check_frontend.sh` still carry citadel-era details. The image ships Yarn 1.22.22, and `frontend_dev` `depends_on: app`.

## Steps

- [01 — Rewrite the frontend agent definition](plan/01-frontend-agent.md)
- [02 — Fix the check scripts](plan/02-check-scripts.md)
- [03 — Add per-agent commit authors](plan/03-git-authors.md)
- [04 — Make infra.md accurate](plan/04-infra-agent.md)

## CI Checks
CircleCI does not check `.claude/`. Verify locally:
- `.claude/scripts/check_frontend.sh` — coverage and lint must be green (the same commands as the CI job `frontend_checks`).
- `.claude/scripts/check_security.sh` — the frontend `yarn audit` line must run. An advisory finding there is reported, not fixed, in this issue.
- `jq . .claude/configuration/arcanum-repo-config.json` — the file must still be valid JSON.

## Notes
- Do not touch `frontend/`, `source/`, Docker/CI files or `docs/agents/specs/`.
- `yarn audit` exits non-zero when it finds advisories. If that happens, report the findings rather than changing dependencies.
