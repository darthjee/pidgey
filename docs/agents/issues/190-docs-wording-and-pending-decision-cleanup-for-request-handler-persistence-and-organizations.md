# Issue: Docs: wording and Pending-decision cleanup for request-handler, persistence and organizations

## Description

Part of Epic #185 (fold `docs/agents/specs/` into `docs/agents/`). The `request-handler`, `persistence` and `organizations` docs now live directly under `docs/agents/`, but they still read as target-state specs: they carry `Purpose:` boilerplate, call themselves "specs", describe a placeholder front controller that no longer exists, and contain one `Pending decision` reference that is already settled.

Scope: `docs/agents/request-handler.md` + `request-handler/**`, `docs/agents/persistence.md` + `persistence/**`, `docs/agents/organizations.md` + `organizations/**` (32 files, about 2,600 lines).

## Problem

- Every file opens with a `Purpose:` paragraph that restates the title in spec-tree language ("index of the specs describing…").
- About 20 spots call these docs, or other docs, "spec(s)" (e.g. "This spec builds on…", "endpoint specs", "api specs", "table spec", "email specs").
- `source/public/.htaccess` and the real `index.php` front controller exist, yet `request-handler/rewrite.md` `## Transition` still says "Today there is no `.htaccess`… placeholder `index.php`…", and `request-handler/front-controller.md` (line 16 and `## Backward compatibility`) still describes replacing the placeholder.
- Testing/Verification sections are introduced as "For the implementation issue(s):", and `handlers.md` says "each service's implementation issue adds its getter".
- `persistence/repositories.md` (`## Secrets`) still calls the SMTP password encryption mechanism "a pending decision of email/smtp-connections.md", although it was decided (#151, implemented in #160) and is defined in `email/password-encryption.md`.

## Expected Behavior

The three areas read as docs of the current system: no `Purpose:` boilerplate, no "spec" self-references, no description of the pre-front-controller transition, and every `Pending decision` mark either still open or recorded as decided with its evidence. No contract content is lost and every link still resolves.

## Solution

### Changes

1. **Drop `Purpose:` paragraphs** (and the blank line after each) in all 32 files — Epic-wide convention decided in #189. Edit paragraph-aware: some wrap over 2–3 lines.
2. **Reword "spec(s)" to "doc(s)"** wherever a file refers to itself or to another doc, matching by text, as #189 did for the api docs. Known spots include `persistence.md:7`, `organizations.md:10`, `request-handler/middlewares.md:7` and `:124`, `request-handler/front-controller.md:18`, `request-handler/static-files.md:56` and `:101`, `request-handler/handlers.md:128`, `:147` and `:156`, `request-handler/routing.md:103`, `request-handler/request-response/processing-request.md:35`, `persistence/repositories.md:62–63`, `:73` and `:143–144`, `persistence/schema-migrations.md:18–19`, `persistence/testing.md:33`, `:58`, `:60` and `:74`, `organizations/entities.md:75`, `organizations/bootstrap.md:16`. Re-grep `\bspec` before finishing; no Jasmine paths are in scope.
3. **Trim the stale transition text (option A):** keep the headings `## Transition` (`rewrite.md`) and `## Backward compatibility` (`front-controller.md`) so the `rewrite.md#transition` anchor and its two links stay valid, and cut their bodies down to the facts that still hold: `/` answers `404` and is not the health check; `GET /health.json` is the health check with the `{"status":"ok"}` payload; `/` reaches `index.php` through `DirectoryIndex` and the router decides the answer; the Vite dev proxy's `*.json` requests reach PHP. Drop the sentences about "today there is no `.htaccess`", the placeholder, "ships before", "once … lands" and "may ship them together". In `front-controller.md:16`, replace "replaces the placeholder …" with neutral wording (or drop it), keeping the link to `#backward-compatibility`.
4. **Neutral test lead-ins:** "For the implementation issue(s):" / "Checks for the implementation issue:" become "Required tests:" / "Required checks:" (`rewrite.md` Verification, `front-controller.md` Testing — keeping "no web server is needed" — and `middlewares.md` Testing). `handlers.md:85` becomes "each service adds its getter when it is introduced". The test lists themselves stay unchanged.
5. **Record the decided Pending reference:** in `persistence/repositories.md` `## Secrets`, replace "The encryption mechanism is a pending decision of [email/smtp-connections.md]…; it blocks the service that encrypts, not the table or the repository." with a pointer to [email/password-encryption.md](../email/password-encryption.md), which defines the mechanism. Evidence for the PR: #151 (decision), #160 (implementation), `email/smtp-connections.md` "defined in password-encryption.md".

### Pending-decision review (recorded only in the PR description, as a per-item table)

- Decided: the encryption reference above.
- Open, unchanged: `request-handler/static-files.md` — conditional GET (`ETag`/`304`), full CSP (mirrors `frontend/screens.md`); `organizations/bootstrap.md` — setup flow / system-organization marking (mirrors `api/endpoints/auth/recovery.md`); `organizations/entities.md` — how users join an organization, the three admin indirect-access items, reinstating a denied user (all consistent with `api/endpoints/admin-users.md`); `request-handler/request-response/request.md:72` — the pending `__Host-access_token` rename (mirrors `api/authentication/access-token.md`).
- "None." / resolved-note sections stay as they are.

### Decisions

- The historical "Hand-offs … (#61) — resolved" notes in `request-handler/request-response.md` and `request-handler/middlewares.md`, and the "decided in #58/#62" notes, stay unchanged: they carry contract statements.
- `target` in `organizations/entities.md` and `organizations/data-model/smtp-connections.md` means the target user/connection, and "deployment docs describe this target" in `rewrite.md` means the deployment target — not target-state markers. `future` in `static-files.md:101`, `rewrite.md:103`, `entities.md:89`, `bootstrap.md:53` and `smtp-connections.md:49` describes open or hypothetical items and stays. "not an option today" (`rewrite.md:127`) and "Nothing needs it today" (`rewrite.md:103`) state current facts and stay.

### Out of scope

`docs/agents/folder-structure.md`'s `*(planned)*` (overview, #191); `docs/agents/issues/`, `docs/agents/plans/`, frontend `specs/` folders, `.claude/state/*.json`; auditing code against docs.

### Shared rules (from Epic #185)

- **Zero contract loss:** endpoints, tables/columns/types/indexes, class and public method signatures, environment variables, error codes and the failure taxonomy are preserved intact. Do not rewrite prose for style.
- No file moved or renamed; every relative link and anchor still resolves.
- Never retarget with a blind global replace of `specs/`.

### Pending-decision review rules

1. An item counts as decided only on evidence in the repo's docs or issues; no code audit; when unclear it stays Pending.
2. A decided item moves into the contract text; open items stay unchanged; mirrored summaries are updated together.
3. "None." and pointer-only `## Pending decisions` sections stay.
4. No new GitHub issue per open item.

### Testing and ownership

Docs-only; owner `architect`. Verify with greps: no `^Purpose:` in scope; no `\bspecs?\b` self-references left; no `placeholder`, `implementation issue`, `Today there is no` in scope; every relative link and anchor in the changed files resolves.

### Acceptance Criteria

- [ ] No `Purpose:` paragraphs remain in the 32 files.
- [ ] No "spec(s)" self-references remain in scope.
- [ ] `rewrite.md#transition` and `front-controller.md#backward-compatibility` keep their headings and state only current facts; no placeholder/transition wording remains.
- [ ] Test lead-ins are neutral; test lists unchanged.
- [ ] `persistence/repositories.md` points to `email/password-encryption.md`; the PR description has the per-item Pending table with evidence for the decided item.
- [ ] Open Pending items and their mirrors are unchanged and consistent.
- [ ] No contract text lost; no file moved; all links resolve.

### Depends on

#186 (merged). Disjoint from #191–#193; #194 audits the result.

## Benefits

The request-handler, persistence and organizations docs describe the system as it is, so agents stop reading resolved transitions or settled items as open work, while every contract stays intact.
