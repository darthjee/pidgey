# Frontend Plan: Codacy partial and final coverage upload

Main plan: [plan.md](plan.md)

## Shared contracts

- Produce: `npm run coverage` still writes `coverage/lcov.info` (relative to `frontend/`), now including every `assets/**` `.js`/`.jsx` file, even ones no spec loads.
- Rely on: infra uploads this file to Codacy, with paths rewritten to `frontend/...`.

## Implementation Steps

### Step 1 — Widen the c8 coverage scope
In `frontend/package.json`, add `"all": true` and `"include": ["assets/**"]` to the existing `c8` block. Keep `reporter` (`text`, `lcov`) and `extension` (`.js`, `.jsx`) as they are. Without `all`, c8 only reports files a spec loaded, which overstates coverage.

### Step 2 — Verify locally
Run `docker compose run --rm <frontend service> npm run coverage`, or `npm run coverage` inside `frontend/`, and confirm:
- `coverage/lcov.info` exists and has `SF:` entries for `assets/` files no spec loads;
- specs still pass (the coverage numbers may drop, which is expected);
- no file outside `assets/` (specs, `specs/support`, config files) appears in the report.
Note in the PR what the `SF:` lines look like (absolute, `./`-relative or bare relative), because infra's path rewrite depends on it.

## Files to Change
- `frontend/package.json` — add `all: true` and `include: ["assets/**"]` to the `c8` block.

## CI Checks
- `frontend/`: `npm run lint` and `npm run coverage` (CI job: `frontend_checks`)

## Notes
- Don't change the `test` (nyc) script or the specs.
