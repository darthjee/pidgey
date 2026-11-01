# Product-owner Plan: Router with 404 fallback for unknown routes

Main plan: [plan.md](plan.md)

## Shared contracts

- `source/config/` (route files) is a new top-level path in `source/` that ships with the release; it is not excluded.

## Implementation Steps

### Step 1 — Update "What ships"
`docs/agents/specs/deployment/security.md` "What ships" requires any new top-level path in `source/` to be listed in the same change. Add `config/` (route files, plus the `.env` link added at release time; outside `public/`, not web-reachable) to the shipped list. If "Known gaps" in that spec mentions the every-path-200 gap, remove it there too.

## Files to Change
- `docs/agents/specs/deployment/security.md` — add `config/` to "What ships"; drop the every-path-200 known gap if listed.

## Notes
- No behaviour decision changes: routing, 404 and error specs are already decided.
