# Cross-references, indexes and pending items
Make the rest of the specs consistent with the new conventions:

- `api.md`: update the conventions row to point to the `api/conventions.md` index (format,
  validation, errors, origin check).
- `frontend/layout.md`: the client normalizes errors into the envelope in
  `api/conventions/errors.md` (update the link).
- `api/endpoints.md`: fix the "Failure" link to `conventions/errors.md`; leave its structure to
  the sibling endpoint sub-issues.
- `api/pending.md`: rewrite the CORS item (now covered by `conventions/origin-check.md`; keep
  only what is undecided, e.g. third-party browser clients), and add a **Pending decision —
  cache headers**: no cache headers for now; revisit if a caching proxy is added or if
  `Cache-Control: no-store` is wanted on credential-bearing responses.
- Grep the specs for leftovers (`snake_case`, `validation_error`, lower-case `smtp_*` codes,
  `422` used for validation) and fix any remaining reference.

## Files to Change
- `docs/agents/specs/api.md` — conventions row.
- `docs/agents/specs/frontend/layout.md` — error-shape link.
- `docs/agents/specs/api/endpoints.md` — failure link only.
- `docs/agents/specs/api/pending.md` — CORS and cache-headers items.
