# Infra Plan: Deployment implementation docs

Main plan: [plan.md](plan.md)

## Overview
Document the deployment **as implemented**. The sources are `.circleci/config.yml`, `.circleci/backend-exclude.txt`, `dockerfiles/circleci_pidgey-base/Dockerfile`, `source/bin/{check-config,migrate}`, `source/public/.htaccess` and `frontend/public/.htaccess`, plus the #100 findings. Where the specs (`docs/agents/specs/deployment/*.md`) describe something that differs from or goes beyond the real setup, the docs follow the real setup and state the difference explicitly.

## Context
- The issue is [#101](../../issues/101-deployment-implementation-docs.md), part of epic #95. #102 deletes the specs later, so the new docs must stand on their own: they must not *depend* on the specs for content, though they may link to non-deployment specs such as `architecture/configuration.md` and `api/pending.md`.
- Differences already known between the specs and the real setup:
  - no `cleanup` step: `0.11.0` lacks it, and the `release` job ends with `deploy_frontend.sh release`;
  - no GitHub tag ruleset: the owner is the only collaborator, so only the owner can push tags;
  - the context's expression restriction is configured in CircleCI (the free plan has no security groups);
  - `serial-group` is `<< pipeline.project.slug >>/pidgey-deploy`.
- Never committed: real host names, SSH users, home paths, folder paths and domains. Use variable names and placeholders (`<live>`, `<domain>`) only.

## Steps

- [01 — Index and pipeline](infra/01-index-and-pipeline.md)
- [02 — Variables](infra/02-variables.md)
- [03 — Server](infra/03-server.md)
- [04 — Operations](infra/04-operations.md)
- [05 — Known gaps](infra/05-known-gaps.md)
- [06 — Related docs](infra/06-related-docs.md)

## CI Checks
- Docs only (`docs/`, `AGENTS.md`): no CI job targets them, so there are no local commands to run.
- Before opening the PR, grep the diff for anything that looks like a real host, user, path or domain (for example `dreamhost`, `/home/`, `.net`, `.com`). Only placeholder and variable names may appear.

## Notes
- The architect reviews the result (issue requirement).
- The docs follow `AGENTS.md`'s granularity rule: a short index plus detail files.
- If #109, #110 or #111 merge before this, describe their outcome instead of the gap.
