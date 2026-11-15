# Plan: Docs: wording and Pending-decision cleanup for request-handler, persistence and organizations

Issue: [190-docs-wording-and-pending-decision-cleanup-for-request-handler-persistence-and-organizations.md](../../issues/190-docs-wording-and-pending-decision-cleanup-for-request-handler-persistence-and-organizations.md)

## Overview

A docs-only cleanup of the 32 files under `docs/agents/request-handler.md` + `request-handler/**`, `docs/agents/persistence.md` + `persistence/**` and `docs/agents/organizations.md` + `organizations/**`. It drops the `Purpose:` boilerplate, rewords "spec" self-references, trims the stale placeholder/transition text, neutralises the "implementation issue" test lead-ins, and records the one settled Pending reference (SMTP password encryption). No contract text is lost, no file moves, and every link and anchor still resolves.

## Context

- Part of Epic #185. #189 already applied the same conventions to the api docs (drop `Purpose:` Epic-wide; "spec(s)" → "doc(s)").
- `source/public/.htaccess` and the real `index.php` (`FrontController::run()`) exist, so the "placeholder" / "Today there is no `.htaccess`" text is stale.
- The SMTP password encryption mechanism was decided in #151, implemented in #160 and is defined in `docs/agents/email/password-encryption.md`.
- Every other Pending item in scope has no repo evidence of being decided and stays unchanged (see the issue's review list).

## Implementation Steps

### Step 1 — Drop the `Purpose:` paragraphs

In each of the 32 files, delete the `Purpose:` paragraph under the title together with the blank line that follows it. Some paragraphs wrap over 2–3 lines (e.g. `persistence.md`, `request-handler/request-response.md`, `organizations/data-model.md`), so edit paragraph-aware: remove from the `Purpose:` line up to (not including) the next blank line, then that blank line. Do not use a line-only `sed`.

Commit: `docs: drop Purpose lines from the request-handler, persistence and organizations docs (#190)`.

### Step 2 — Reword self-references and stale target-state text

Match by text, not line number (Step 1 shifts lines). Edit each spot:

1. **"spec(s)" → "doc(s)"** wherever a file names itself or another doc, as in #189: `persistence.md` ("This spec builds on"), `organizations.md` ("table specs"), `request-handler/middlewares.md` ("api specs", "linked specs"), `request-handler/front-controller.md` ("api specs"), `request-handler/static-files.md` ("this spec" ×2), `request-handler/handlers.md` ("email specs", "endpoint specs", "There is no separate spec" → "There is no separate doc"), `request-handler/routing.md` ("endpoint specs"), `request-handler/request-response/processing-request.md` ("This spec owns"), `persistence/repositories.md` ("the specs require", "names the spec", "endpoint specs", "table spec defines", "this spec"), `persistence/schema-migrations.md` ("table spec" ×2), `persistence/testing.md` ("endpoint spec", "table specs", "spec'd tables" → "documented tables", "table spec"), `organizations/entities.md` ("endpoint specs"), `organizations/bootstrap.md` ("endpoint spec"). Re-grep `\bspec` across the scope afterwards; nothing in scope is a Jasmine path.
2. **`request-handler/rewrite.md` `## Transition`:** keep the heading. Replace the body with the facts that still hold: `/` is not the health check — it is the document-root directory, the rule does not rewrite it, it reaches `index.php` through `DirectoryIndex`, and the router decides the answer (`404`); `GET /health.json` ([api/endpoints/operational.md](../api/endpoints/operational.md)) is the health check with the `{"status":"ok"}` payload; the Vite dev proxy's `*.json` requests reach PHP. Drop "Today there is no `.htaccess`…", the placeholder sentences, "once the front controller lands", "If the `.htaccess` ships before…" and "may ship them together".
3. **`request-handler/front-controller.md`:** replace "It **replaces the placeholder** `source/public/index.php`, which answers a static `{"status":"ok"}` on every path (see [Backward compatibility](#backward-compatibility))." with a neutral sentence that keeps the link (e.g. "`source/public/index.php` only boots it; see [Backward compatibility](#backward-compatibility) for the `/` and `/health.json` behaviour."). In `## Backward compatibility`, keep the heading, drop "Replaces the placeholder `index.php` …", and keep the `/health.json` and `/` → `404` bullets (the `rewrite.md#transition` link stays).
4. **Test lead-ins:** `rewrite.md` "Checks for the implementation issue:" → "Required checks:"; `front-controller.md` "For the implementation issues; no web server is needed." → "Required tests; no web server is needed."; `middlewares.md` "For the implementation issues:" → "Required tests:"; `handlers.md` "each service's implementation issue adds its getter" → "each service adds its getter when it is introduced". The lists stay unchanged.
5. **`persistence/repositories.md` `## Secrets`:** replace "The encryption mechanism is a pending decision of [email/smtp-connections.md](../email/smtp-connections.md); it blocks the service that encrypts, not the table or the repository." with "The encryption mechanism is defined in [email/password-encryption.md](../email/password-encryption.md)."

Leave unchanged: the "Hand-offs … — resolved" notes, the "decided in #58/#62" notes, `target`/`future`/`today` uses that state current facts or open items, and every open `## Pending decisions` item.

Commit: `docs: reword spec self-references and stale transition text (#190)`.

## Files to Change

- All 32 files in scope — Step 1 (`Purpose:` removal).
- `docs/agents/persistence.md`, `docs/agents/organizations.md`, `docs/agents/request-handler/{middlewares,front-controller,static-files,handlers,routing,rewrite}.md`, `docs/agents/request-handler/request-response/processing-request.md`, `docs/agents/persistence/{repositories,schema-migrations,testing}.md`, `docs/agents/organizations/{entities,bootstrap}.md` — Step 2.

## Notes

- **Verification greps** (scope = the 32 files): `grep -n '^Purpose:'` → empty; `grep -niE '\bspec'` → only intended non-self-reference hits, if any; `grep -niE 'placeholder|implementation issue|Today there is no'` → empty except `repositories.md`'s "named placeholders" (SQL); every relative link and `#anchor` in the changed files resolves (in particular `rewrite.md#transition` and `front-controller.md#backward-compatibility`).
- **PR description:** include a per-item Pending table — decided: SMTP password encryption (evidence #151, #160, `email/smtp-connections.md` → `email/password-encryption.md`); open, unchanged: ETag/304 and full CSP (`static-files.md`), setup flow / system organization (`bootstrap.md`), joining an organization, the three admin indirect-access items and reinstating a denied user (`entities.md`, consistent with `api/endpoints/admin-users.md`), `__Host-access_token` (`request.md`).
- Zero contract loss: review the diff so only wording, the trimmed transition text and the encryption pointer change.
