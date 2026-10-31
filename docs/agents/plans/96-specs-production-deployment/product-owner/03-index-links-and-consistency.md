# Index, inbound links and consistency pass

Make every reference point at where its content now lives, and check the whole change set is
consistent.

- `docs/agents/specs.md`: update the `deployment` row's purpose (CI, tag-triggered production
  deploy, server layout, security).
- Inbound links: point each one at the file its content moved to. For example, the auth
  rate-limit recommendation now links `deployment/security.md` and `deployment/production.md`.
  The files are:
  - `request-handler/rewrite.md`;
  - `api/pending.md`, `api/endpoints/auth/session.md`, `api/endpoints/operational.md`;
  - `frontend/testing.md`;
  - `docker/makefile.md`;
  - `architecture/repository-layout.md`.

  Re-run `grep -rn "deployment" docs/agents --include=*.md` to catch any missed.
- Consistency checks:
  - tag pattern, job names, variable names, the `config/.env` path and the exclude-list path
    are spelled the same everywhere;
  - no real host value appears anywhere;
  - every settled decision is gone from its "Pending decisions" section, and every
    still-open one is listed.
- **For the architect (outside the product-owner's scope):** in `docs/agents/contributing.md`,
  replace the planned deploy rows of the jobs table with the real job names from
  `deployment/pipeline.md`, still marked *(planned)*.

## Files to Change
- `docs/agents/specs.md`
- `docs/agents/specs/request-handler/rewrite.md`
- `docs/agents/specs/api/pending.md`
- `docs/agents/specs/api/endpoints/auth/session.md`
- `docs/agents/specs/api/endpoints/operational.md`
- `docs/agents/specs/frontend/testing.md`
- `docs/agents/contributing.md` (architect)
