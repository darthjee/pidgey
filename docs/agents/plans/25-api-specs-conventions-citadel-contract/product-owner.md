# Plan: API specs: conventions (Citadel contract)

Issue: [25-api-specs-conventions-citadel-contract.md](../../issues/25-api-specs-conventions-citadel-contract.md)

## Overview
Replace `docs/agents/specs/api/conventions.md` with a short index of a new
`docs/agents/specs/api/conventions/` folder holding the shared API rules taken from the Citadel
contract (format and paths, validation, errors, origin check, guard order), adapted by the
issue's decisions. Then align every spec that references the old conventions. No code,
migrations or frontend changes.

## Context
- The copied admin frontend calls the Citadel contract; Pidgey's specs follow it (epic #24).
- Decisions from the issue:
  - One error envelope everywhere, including `POST /v1/messages`: upper-case codes,
    `400 VALIDATION_FAILED` with `details` as strings, SMTP reply in `error.smtp`,
    `statusCode` + `timestamp`; send-failure statuses kept (409/502/503/504/500).
  - No cache headers (Citadel 2.5 and porting note 9 not carried over); recorded as pending.
  - `*.json` paths always go to the API, anywhere, including under `/admin/`; only
    `POST /v1/messages` keeps the `/v1` prefix.
  - `api/conventions.md` becomes an index of `api/conventions/`.
- The Citadel excerpts (sections 1, 2 and 10) are embedded in the issue file; use them as the
  source text, dropping Citadel source links, the `CITADEL_PLACEHOLDER_` prefix and Tent.
- Spec format (`docs/agents/specs.md`): title, one-line `Purpose:`, content, explicit
  **Pending decision** items, English only.

## Steps

- [01 — Create the conventions index and format rules](product-owner/01-conventions-index-and-format.md)
- [02 — Validation rules](product-owner/02-validation.md)
- [03 — Error envelope, codes and statuses](product-owner/03-errors.md)
- [04 — Origin check and CORS](product-owner/04-origin-check.md)
- [05 — Align email failure codes and send flow](product-owner/05-email-failures.md)
- [06 — Align routing: backend rule and Vite dev proxy](product-owner/06-routing.md)
- [07 — Cross-references, indexes and pending items](product-owner/07-cross-references.md)

## Notes
- The `security` agent (read-only) should review steps 03 and 04 (enumeration-safe errors,
  the "never `401` for a business rule" rule, origin check), since they define security
  behavior other specs rely on.
- `api/endpoints.md` is rewritten by sibling sub-issues of #24; here only fix its link to the
  conventions and the `422` wording if still present, without restructuring it.
- `frontend/vite.config.js` is **not** changed here (a later implementation issue).
- The auth model (cookie, refresh tokens, gate) belongs to the "Authentication model"
  sub-issue; conventions only reference "the gate" and "the admin check" by name in the guard
  order, linking to `api/authentication.md`.
