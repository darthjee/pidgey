# Plan: Create the nodejs and cli agents

Issue: [243-create-the-nodejs-and-cli-agents.md](../../issues/243-create-the-nodejs-and-cli-agents.md)

## Overview

Create the `nodejs` and `cli` agents that own `clients/nodejs/` and `clients/cli/`, and update the existing agents and layout docs so client work has an owner and a routing target before `clients/` exists. Docs and agent files only: no package code, CI, `Makefile` or compose change.

## Context

- Epic #219 (wave 2). The specs under `docs/agents/specs/node-client.md` and `docs/agents/specs/node-client/*.md` are the source of truth; ownership comes from `packaging.md`, section Ownership.
- Every file touched is owned by `architect` (`.claude/`, `docs/agents/folder-structure.md`, `docs/agents/architecture/`), so there is no specialist split.
- The `Clients` row of `AGENTS.md`, the `clients` row of `docs/agents/specs.md`, the `contributing.md` check-jobs row and the as-built `docs/agents/clients*` docs land with #246; the *(planned)* markers and the exact commands are filled in by #246 (`nodejs.md`, library and `clients/scripts/` rows) and #249 (`cli.md`, CLI row).

## Steps

- [01 — Create the nodejs agent](plan/01-create-the-nodejs-agent.md)
- [02 — Create the cli agent](plan/02-create-the-cli-agent.md)
- [03 — Update the architect agent](plan/03-update-the-architect-agent.md)
- [04 — Update the backend, frontend, infra and product-owner agents](plan/04-update-the-other-agents.md)
- [05 — Add the planned clients/ rows to the layout docs](plan/05-add-the-planned-clients-rows.md)
- [06 — Verify links, frontmatter and routing](plan/06-verify.md)

## CI Checks

- No CI job covers `.claude/` or `docs/agents/`: `backend_checks`, `backend_tests` and `frontend_checks` are unaffected. Verification is the manual check of step 06.

## Notes

- `architect.md` ends with "When a new agent is created or its scope changes, update this file and `AGENTS.md`." `AGENTS.md` has no agents table, only the documentation index, whose `Clients` row is deliberately left to #246: no `AGENTS.md` change here.
- The *(planned)* Commands must not invent a compose service name: name the `yarn` scripts and the rule that they run in the compose Node service, and leave the exact lines to #246 and #249.
- The agents' Stack values (`>=22.12.0`, `c8` 100 %, ESLint 9 flat, `tsc --allowJs --checkJs --noEmit`) are copied from the specs; if they disagree, the spec wins and the agent file is corrected.
