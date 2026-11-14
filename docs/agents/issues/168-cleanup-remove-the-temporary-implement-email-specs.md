# Issue: Cleanup: remove the temporary implement-email specs

## Description
Part of epic #149 (email integration). The last issue of the epic: removes the temporary implementation spec (`docs/agents/specs/implement-email.md` and `implement-email/`) once everything is implemented.

## Solution
1. Check every item of `docs/agents/specs/implement-email/*.md` is ticked. Three are currently open and must be verified against the code: `encryption.md` item 7 (endpoint and transport use), `endpoints.md` `SMTP_TEST_MAX_ATTEMPTS`/`SMTP_TEST_WINDOW` (#166), `frontend.md` item 7 (manual check against the development mailpit). Tick the ones that are done; for anything genuinely missing, spin off a follow-up issue instead of dropping it.
2. Move anything still useful into the permanent docs (`architecture.md`, `testing/`, …). In particular, fold the CI rules from `implement-email/testing.md` into the permanent `docs/agents/specs/testing/` and repoint `docs/agents/deployment/pipeline.md` (line 33) there.
3. Delete `docs/agents/specs/implement-email.md` and the `implement-email/` folder, and remove the hub row in `docs/agents/specs.md`.

Dependencies: #160–#167. Agents: architect, product-owner.

## Expected Behavior / Acceptance
A grep proves that no file under `docs/agents/` links to `implement-email`, excluding the historical `docs/agents/issues/` and `docs/agents/plans/` folders.
