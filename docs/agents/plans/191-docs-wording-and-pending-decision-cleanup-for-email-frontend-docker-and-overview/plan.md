# Plan: Docs: wording and Pending-decision cleanup for email, frontend, docker and overview

Issue: [191-docs-wording-and-pending-decision-cleanup-for-email-frontend-docker-and-overview.md](../../issues/191-docs-wording-and-pending-decision-cleanup-for-email-frontend-docker-and-overview.md)

## Overview

A docs-only cleanup of the 24 files under `docs/agents/email.md` + `email/**`, `frontend.md` + `frontend/**`, `docker.md` + `docker/**` and `overview.md` + `overview/**`, plus one mirrored line in `docs/agents/api/authentication/sessions.md`. It drops the `Purpose:` boilerplate, rewords non-Jasmine "spec(s)" to "doc(s)", records #40/#41 as done (M1/M2 resolved) and neutralises the `docker/volumes.md` "target design" note. No contract text is lost, no file moves, and every link and anchor still resolves.

## Context

- Part of Epic #185. #189 and #190 already applied the same conventions to the api, request-handler, persistence and organizations docs (drop `Purpose:`; "spec(s)" → "doc(s)").
- #40 ("Frontend: send organizationName on register") and #41 ("Frontend: single-flight refresh across requests and tabs") are closed as completed by PRs #48 and #49, which counts as evidence under the issue's rules; no code audit.
- The `docker/volumes.md` "Future work" volumes do not exist, so their rows stay.
- No email or frontend `Pending decision` item has evidence of being decided; all stay unchanged.

## Implementation Steps

### Step 1 — Drop the `Purpose:` paragraphs

In each of the 24 files, delete the `Purpose:` paragraph under the title together with the blank line that follows it. Four paragraphs wrap over 2 lines (`email/testing.md`, `email/password-encryption.md`, `email/send-flow.md`, `frontend/api-compatibility.md`), so edit paragraph-aware: remove from the `Purpose:` line up to (not including) the next blank line, then that blank line. Do not use a line-only `sed`.

Commit: `docs: drop Purpose lines from the email, frontend, docker and overview docs (#191)`.

### Step 2 — Reword self-references and settled target-state text

Match by text, not line number (Step 1 shifts lines). Edit each spot:

1. **"spec(s)" → "doc(s)"** where it names these docs or the API docs:
   - `overview.md` index row: "Domain terms used across all specs." → "Domain terms used across all docs.";
   - `overview/terminology.md`: the "used consistently across all specs and code" wording goes with the `Purpose:` line in Step 1; nothing else to change there;
   - `email/testing.md`: "updating compose, CI and the specs together" → "the docs together";
   - `frontend/development.md`: "live in the docker specs" → "live in the docker docs";
   - `frontend/screens.md`: "whether the call matches the spec" → "the API docs";
   - `frontend/layout.md`: "its status against the API spec" → "the API docs";
   - `frontend/testing.md`: "copies of the API spec's example responses" and "an example response in the API spec" → "API docs";
   - `frontend/api-compatibility.md`: "the endpoint specs in [api/endpoints]" → "endpoint docs"; "exact code from the spec" → "from the API docs"; "matches the spec" (legend and the SMTP connection rows note) → "matches the API docs"; "the spec stays and a frontend follow-up is opened; no API spec was found wrong" → "the API doc stays … no API doc was found wrong"; "copied from the API spec's example responses" → "API docs'"; every table header `| Spec |` → `| Doc |` (7 tables).
   - **Keep** the Jasmine meanings: `frontend/testing.md` `## Specs`, "a spec can change it freely", "Specs that need…", `eslint assets specs`, "lints `assets` and `specs`", "their Jasmine specs"; `frontend/api-compatibility.md` "Their Jasmine specs"; `frontend/layout.md` `specs/` tree and "Node specs". Re-grep `\bspec` across the scope afterwards.
2. **#41 (PR #49), single-flight refresh:**
   - `frontend/screens.md` `### Refresh single flight`: delete the paragraph "Target behaviour, follow-up #41 (today every `401` starts its own refresh, see [api-compatibility.md](api-compatibility.md#cross-cutting-behaviour))." The `Why:` and `Rules:` that follow stay as the current rule.
   - `frontend/api-compatibility.md` `POST /auth/refresh.json` row: replace "**M2**: no single flight, so parallel `401`s re-present a rotated token and trigger replay revocation (#41)" with a note that single flight is in place (M2, closed by #41), keeping the ✅ for the shape. The cross-cutting "Single-flight refresh…" row status `**M2**, #41` → `✅ (M2, closed by #41)`.
   - `api/authentication/sessions.md` (Rotation/replay text): "(see the single-flight rule in [frontend/screens.md](…#refresh-single-flight); the frontend change is follow-up #41)" → keep the link, drop "the frontend change is follow-up #41" (e.g. "…; done in #41").
3. **#40 (PR #48), register `organizationName`:**
   - `frontend/screens.md` Register bullet: drop "(target, #40; without it every registration answers `400 VALIDATION_FAILED`)"; the field stays as `organizationName`.
   - `frontend/api-compatibility.md` `POST /auth/register.json` row: replace "**M1**: `organizationName` is required but not sent, so every registration answers `400 VALIDATION_FAILED` (#40)." with "✅ (`organizationName` sent; M1, closed by #40)." and keep the `password_confirmation` sentence unchanged.
   - Keep the M1–M6 legend line so M3 and the other ids keep their meaning.
4. **`docker/volumes.md`:** "Rows marked "Future work" are kept as the target design but are not part of the initial setup." → "Rows marked "Future work" are not set up yet." The two rows stay.

Leave unchanged: every open `## Pending decisions` item and inline `Pending decision` (`email/send-flow.md` ×2, `email/message-scope.md`, `email/smtp-connections.md`, `frontend/screens.md` CSP and `HttpOnly` cookie), the "None." sections, M3 (`localStorage`, a v1 known limitation), and `target`/`placeholder` uses that state current facts (the SMTP connect target, Makefile targets, build targets, the proxy target, the `docker_volumes` placeholder files, the `(string)`-cast placeholder in `send-flow.md`).

Commit: `docs: reword spec self-references and record #40/#41 as done (#191)`.

## Files to Change

- All 24 files in scope — Step 1 (`Purpose:` removal).
- `docs/agents/overview.md`, `docs/agents/email/testing.md`, `docs/agents/frontend/{development,screens,layout,testing,api-compatibility}.md`, `docs/agents/docker/volumes.md`, `docs/agents/api/authentication/sessions.md` — Step 2.

## Notes

- **Verification greps** (scope = the 24 files): `grep -n '^Purpose:'` → empty; `grep -niE '\bspecs?\b'` → only the Jasmine hits listed above; `grep -n 'M1\|M2\|#40\|#41'` → only the resolved notes and the legend; `grep -niE 'target behaviour|target design|\(target'` → empty; every relative link and `#anchor` in the changed files resolves (in particular `screens.md#refresh-single-flight` and `api-compatibility.md#cross-cutting-behaviour`).
- **PR description:** include a per-item Pending/target table — decided by closed issues: single-flight refresh (#41, PR #49), register `organizationName` (#40, PR #48); open, unchanged: `messages` log table and host-check gaps (`send-flow.md`), future message scope (`message-scope.md`), non-active connection on `POST /v1/messages` (`smtp-connections.md`), CSP and `HttpOnly` refresh cookie (`screens.md`, consistent with `api/authentication/sessions.md`).
- `api/authentication/sessions.md` is outside the four areas; it is touched only as the mirror of #41, per the issue's mirrored-summary rule.
- Zero contract loss: review the diff so only wording, the resolved M1/M2 status and the volumes note change.
