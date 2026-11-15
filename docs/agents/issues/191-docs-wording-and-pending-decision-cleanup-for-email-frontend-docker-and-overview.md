# Issue: Docs: wording and Pending-decision cleanup for email, frontend, docker and overview

## Description

Part of Epic #185 (fold `docs/agents/specs/` into `docs/agents/`). After the move (#186), the email, frontend, docker and overview docs live directly under `docs/agents/` but still read as a target-state spec tree, and some carry target markers or `Pending decision` marks that are already settled.

Scope (24 files): `docs/agents/email.md` + `email/**`, `frontend.md` + `frontend/**`, `docker.md` + `docker/**`, `overview.md` + `overview/**`. Out-of-area exception: the mirrored summary in `api/authentication/sessions.md` (follow-up #41 mention).

## Problem

- Every file in scope opens with a `Purpose:` paragraph, and the hubs describe themselves as "index of the specs describing…"; `overview/terminology.md` says "terms used consistently across all specs".
- Non-Jasmine "spec(s)" wording remains (e.g. `email/testing.md:42`, `frontend/development.md:5`, the API "spec" mentions and the `Spec` column in `frontend/api-compatibility.md`).
- `frontend/screens.md` still calls the refresh single flight "Target behaviour, follow-up #41" and the register `organizationName` field "target, #40", and `frontend/api-compatibility.md` lists them as mismatches **M1** and **M2**, although #40 and #41 are closed as completed (PRs #48, #49).

## Expected Behavior

- No `Purpose:` paragraph remains in the scope files (same convention as #190).
- Docs refer to themselves and the API docs as "docs", never "specs"; Jasmine "specs" (test files, `## Specs` in `frontend/testing.md`, `eslint assets specs`, "Node specs", `specs/` paths) are untouched.
- The #40/#41 behaviour reads as current, the M1/M2 rows are resolved, and the mirror in `api/authentication/sessions.md` agrees.
- Every still-open `Pending decision` is unchanged.

## Solution

1. **Drop `Purpose:` paragraphs** in all 24 files.
2. **Reword "spec(s)" → "doc(s)"** where it means these docs or the API docs: hub index rows, `overview/terminology.md`, `email/testing.md:42`, `frontend/development.md:5`, `frontend/screens.md:7`, `frontend/layout.md:34`, `frontend/testing.md` (the "API spec's example responses" lines), and `frontend/api-compatibility.md` prose plus its `Spec` table column (→ `Doc`). Jasmine meanings stay.
3. **Settled by closed issues:**
   - #41 (PR #49): `frontend/screens.md` `### Refresh single flight` drops "Target behaviour, follow-up #41 (today every `401` starts its own refresh…)" and states the rule as current; `api-compatibility.md` refresh row and the cross-cutting single-flight row become ✅ (M2 resolved, #41 named); `api/authentication/sessions.md` drops "the frontend change is follow-up #41".
   - #40 (PR #48): `frontend/screens.md` register field drops "(target, #40; without it every registration answers `400 VALIDATION_FAILED`)"; the `api-compatibility.md` register row becomes ✅ for `organizationName` (M1 resolved, #40 named), keeping the `password_confirmation` note.
   - The M1–M6 legend line stays, so the remaining ids keep their meaning.
4. **`docker/volumes.md`:** the "Future work" rows stay (those volumes do not exist yet); only "kept as the target design but are not part of the initial setup" becomes a neutral "are not set up yet".
5. **Pending decisions — all stay open** (no evidence of a decision): `email/send-flow.md` (`messages` log table; host-check gaps), `email/message-scope.md` (future scope list), `email/smtp-connections.md` (non-active connection on `POST /v1/messages`), `frontend/screens.md` (CSP; `HttpOnly` refresh cookie). "None." sections stay.
6. The PR description carries a per-item Pending table with the evidence (or "no evidence, stays open").

Shared Epic rules apply: zero contract loss, no file moved or renamed, every relative link resolves, never a blind replace of `specs/`; out of scope: `issues/`, `plans/`, frontend `specs/` folders, `.claude/state/*.json`, auditing code against the docs.

## Acceptance Criteria

- [ ] No `Purpose:` paragraph and no non-Jasmine "spec(s)" self-reference remain in the scope files.
- [ ] #40/#41 behaviour reads as current; M1/M2 resolved in `api-compatibility.md`; `api/authentication/sessions.md` mirror consistent.
- [ ] Every `Pending decision` reviewed; open ones unchanged; PR description has the per-item table.
- [ ] No contract text lost; no file moved or renamed; all relative links resolve.

## Benefits

The email, frontend, docker and overview docs read as current documentation, with resolved mismatches no longer reported as open, finishing the area cleanup before the entry-point rewrite (#192) and the final audit (#194).
