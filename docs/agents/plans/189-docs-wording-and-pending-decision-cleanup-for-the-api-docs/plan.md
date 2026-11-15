# Plan: Docs: wording and Pending-decision cleanup for the api docs

Issue: [189-docs-wording-and-pending-decision-cleanup-for-the-api-docs.md](../../issues/189-docs-wording-and-pending-decision-cleanup-for-the-api-docs.md)

## Overview

This plan cleans up the wording in `docs/agents/api.md` and `docs/agents/api/**` (32 files):

- delete every `Purpose:` paragraph, applying the Epic-wide convention decided in #189;
- change the remaining "spec(s)" self-references to "doc(s)";
- record the review of the 24 `Pending decision` items in the PR description. All of them stay open, so no Pending text changes.

The work is docs only, owned by `architect`, in a single PR.

## Context

- Part of Epic #185. #186 moved the tree, and these files now live directly under `docs/agents/`.
- **Target-state markers:** exploration found none. `target` always means the target user or connection. The three `future` uses describe open Pending items and stay.
- **`Pending decision` review:** no item has repo evidence of being decided. No other doc settles any of them. The closed issues #27, #28, #30, #31, #128, #129, #132, #139 and #166 created these marks, so they are not evidence of a decision. No item's own file documents the behavior as implemented. The mirrored summaries are already consistent: `api/authentication.md`, `api/endpoints/pidgey-admin.md`, `api/pending.md` and `organizations/entities.md`.
- **Out of scope:**
  - `architecture/configuration.md:203` (its mismatch with `recovery.md` is noted on #194);
  - other areas' `Purpose:` lines (#190, #191, #194);
  - `docs/agents/issues/`, `docs/agents/plans/`, the frontend `specs/` folders and `.claude/state/*.json`.

## Implementation Steps

### Step 1 — Delete the `Purpose:` paragraphs

In each of the 32 files under `api.md` / `api/**`, delete the paragraph that starts with `Purpose:` on line 3. That paragraph runs one to three lines, up to the next blank line. Also delete the blank line that follows it, so the title is followed by one blank line and then the next content. Do this by hand, file by file, or with a paragraph-aware edit. Do **not** run a line-only `sed` delete, because most paragraphs wrap over several lines.

Check that `grep -rn '^Purpose:' docs/agents/api.md docs/agents/api` is empty. Check that no wrapped line is left behind: the line after each `# Title` must be blank, followed by a table, a heading or body prose that was already there.

Commit: `docs: drop Purpose lines from the api docs (#189)`.

### Step 2 — Reword the remaining "spec" self-references

Change "spec(s)" to "doc(s)" in these places only:

- `api.md:7`: "endpoint specs" → "endpoint docs" in the index table;
- `api/authentication.md:13`: "endpoint specs" → "endpoint docs";
- `api/conventions/validation.md:68` ("Endpoint specs") and `:77` ("an endpoint spec");
- `api/conventions/errors.md:99`, `:161` and `:168` ("endpoint specs");
- `api/conventions/format.md:38` ("endpoint spec") and `:42` ("each endpoint spec").

Line numbers are from before Step 1; after Step 1 they shift up by the size of each file's deleted paragraph. Match by text, not by line number. Never use a global replace of `specs`. `api/endpoints/pidgey-admin/smtp-connections.md:14` (`frontend/specs/support/fixtures/smtpConnections/`) is a Jasmine path and must stay unchanged.

Check that `grep -rnIiw 'specs\?' docs/agents/api.md docs/agents/api` returns only the Jasmine path line.

Commit: `docs: reword spec self-references in the api docs (#189)`.

## Files to Change

- `docs/agents/api.md` and all 31 files under `docs/agents/api/`: delete the `Purpose:` paragraph.
- `docs/agents/api.md`, `api/authentication.md`, `api/conventions/validation.md`, `api/conventions/errors.md`, `api/conventions/format.md`: reword "spec(s)" to "doc(s)".

## Verification

- `grep -rn '^Purpose:'` over the scope is empty.
- A whole-word `spec`/`specs` grep over the scope matches only `smtp-connections.md:14`.
- `git diff --stat` touches only the 32 files. `git diff` shows only removed `Purpose:` paragraphs, removed blank lines and the 9 word changes. No contract text changes, and no `## Pending decisions` section changes.
- No file was moved or renamed (`git diff --name-status` shows only `M`). Every relative link still resolves, since no link targets or anchors changed. The `Purpose:` lines are not headings, so no anchor changes.

## PR description

Include a **Pending-decision review** table with one row per item: item, file, verdict (`open`), and evidence checked ("no other doc decides it; no closing issue; not documented as implemented"). The 24 items are:

| # | Item | File(s) |
|---|---|---|
| 1 | rate limits (incl. per-IP throttle of public auth endpoints) | `api/pending.md` |
| 2 | size limits (body, subject/text/html) | `api/pending.md` |
| 3 | idempotency (`Idempotency-Key`) | `api/pending.md` |
| 4 | pagination for list endpoints | `api/pending.md` |
| 5 | CORS for third-party browser clients | `api/pending.md` |
| 6 | cache headers | `api/pending.md` |
| 7 | finer-grained Bearer scopes | `api/authentication/api-tokens.md` (summary in `api/authentication.md`) |
| 8 | token expiration (`expires_at`) | `api/authentication/api-tokens.md` (summary in `api/authentication.md`) |
| 9 | cookie rename to `__Host-access_token` | `api/authentication/access-token.md` (summary in `api/authentication.md`) |
| 10 | cleanup of `refresh_tokens` | `api/authentication/sessions.md` (summary in `api/authentication.md`) |
| 11 | refresh-token storage in the browser (`HttpOnly` cookie) | `api/authentication/sessions.md` (summary in `api/authentication.md`) |
| 12 | absolute session lifetime (`SESSION_MAX_AGE`) | `api/authentication/sessions.md` (summary in `api/authentication.md`) |
| 13 | username character set | `api/endpoints/auth.md` |
| 14 | email-verification flow | `api/endpoints/auth.md` |
| 15 | marking the system organization | `api/endpoints/auth/recovery.md` |
| 16 | outbox and worker for system emails | `api/endpoints/auth/recovery.md` |
| 17 | device-authorization verification code | `api/endpoints/auth/device-authorization.md` |
| 18 | retention of `authorization_requests` rows | `api/endpoints/auth/device-authorization.md` |
| 19 | reinstating a denied user | `api/endpoints/admin-users.md` (mirrored in `organizations/entities.md`) |
| 20 | notifying the old email on an admin email change | `api/endpoints/admin-users.md` (mirrored in `organizations/entities.md`) |
| 21 | persisted audit table of admin actions | `api/endpoints/admin-users.md` (mirrored in `organizations/entities.md`) |
| 22 | forbidding an email and password change in one admin edit | `api/endpoints/admin-users.md` (mirrored in `organizations/entities.md`) |
| 23 | `Cache-Control: no-store` on the token create response | `api/endpoints/pidgey-admin/api-tokens.md` |
| 24 | validation message templates for other rules (e.g. booleans) | `api/conventions/validation.md` |

## Notes

- The PR is docs only, so no backend or frontend test suite applies. CI runs as usual.
- If the implementer finds evidence that an item *is* decided, they must stop and leave it Pending. The review rules allow a change only on repo evidence, and the issue records the verdict "all open".
