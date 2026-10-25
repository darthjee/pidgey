# Product Owner Plan: Frontend setup specs: dev environment, release and naming

Main plan: [plan.md](plan.md)

## Overview
Update the frontend specs and every spec that references the frontend setup, so they describe
the dev environment (`vite_pidgey-base`/`vite_pidgey`, `frontend_dev`, Vite `/v1` proxy), the
release (`make build-fe-release` → `source/public/admin`, `/admin/`), the naming (citadel layout),
and the remaining Pending decisions. Specs describe *what* and *why*; `docker/*.md` and
`deployment/pipeline.md` stay the single definition of images, services, targets and CI jobs, and
the frontend specs link to them.

## Context
Shared names fixed by this issue (used unchanged by #17–#19):

| Name | Value |
| --- | --- |
| Compose service | `frontend_dev`, host port `${FRONTEND_PORT:-8030}` → container 8080 |
| Images | `vite_pidgey-base` (`dockerfiles/vite_pidgey-base/Dockerfile`, repo-root context, `<version>`/`<version>-arm64`), `vite_pidgey` (`dockerfiles/vite_pidgey/Dockerfile`, `ARG BASE_SUFFIX`) |
| Make variable | `FE_BASE_VERSION` (`0.0.1`), `FE_RELEASE_DIR` (`source/public/admin`) |
| Make targets | `build-fe-base`, `push-fe-base`, `build-fe`, `fe-install`, `build-fe-release` |
| Release folder / URL | `source/public/admin`, `/admin/`; Vite `base: '/admin/'` in dev and build |
| Proxy | `/v1` → `http://app:80`, `changeOrigin: false` |
| Naming | `eslint.config.mjs`; specs in `frontend/specs/assets/js/**`, named `*Spec.js`; helpers in `frontend/specs/support/` |
| CI job | `frontend_checks` on `darthjee/circleci_node:0.2.1` (lint + coverage) |

## Steps

- [01 — Add frontend/development.md and update the index](product-owner/01-development-spec.md)
- [02 — Rewrite frontend/build.md as build and release](product-owner/02-build-and-release.md)
- [03 — Fix frontend naming: layout.md and testing specs](product-owner/03-layout-and-testing.md)
- [04 — Update the docker specs](product-owner/04-docker-specs.md)
- [05 — Update pipeline, architecture and overview specs](product-owner/05-pipeline-architecture-overview.md)
- [06 — README and folder-structure (architect)](product-owner/06-readme-and-folder-structure.md)

## CI Checks
- Docs only; no CI job covers `docs/`. Check by hand: relative links resolve, and
  `grep -rn "static" docs/agents README.md` finds no reference to the old `static/` frontend
  location; no spec still calls `frontend_dev`, `./frontend`, `./docker_volumes/node_modules` or
  `frontend_checks` "future work".

## Notes
- Never resolve a Pending decision beyond what the issue records (A/B/C production delivery,
  `/admin/` rewrite, Codacy frontend coverage stay pending).
- Step 06 touches `README.md` and `docs/agents/folder-structure.md`, which are outside the
  product-owner's scope (owned by `architect`); the architect applies that step itself.
- `layout.md` keeps the **target** client list; the copied citadel clients are only described in
  a "Current state" note.
