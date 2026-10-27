# Product Owner Plan: Specs: request-handler — Apache rewrite (.htaccess)

Main plan: [plan.md](plan.md)

## Overview

The product owner writes `rewrite.md` from the issue's **Solution** section, which already holds every decision and every verified observation, then adds cross-links from `architecture/backend.md` and `frontend/build.md`. The architect applies nothing in this plan; everything is under `docs/agents/specs/`.

## Context

- #56 is merged (PR #64): `docs/agents/specs/request-handler.md` (index with flow diagram and parts table) and the six stubs under `docs/agents/specs/request-handler/` exist. The `rewrite.md` stub holds the title, the settled `Purpose:` line and a `**Pending decision:** content to be specified in #57` marker.
- Index sync rules from #56 (comment on #55 and #57): fill the stub (drop the stub marker), update the index diagram/table only if the flow changes, keep the routing rules in `architecture/backend.md`.
- Conventions (`docs/agents/specs.md`): title + one-line `Purpose:`, **Pending decision** markers, English only, hyphenated filenames.

## Steps

- [01 — Write rewrite.md](product-owner/01-write-rewrite-spec.md)
- [02 — Cross-link from related specs](product-owner/02-cross-links.md)
- [03 — Verify](product-owner/03-verify.md)

## Notes

- **Do not re-decide anything.** Every choice in the issue was settled in the enhancement and refinement dialogue and verified on `php:8.4-apache` (dev) and on the production host (oak, DreamHost) on 2026-10-27. Write it down as the issue states it.
- **Hosting conflict, out of scope here.** The issue states that production runs on a DreamHost shared host like oak (mod_fcgid, `.htaccess` only, no vhost access). Other specs still describe a different target: `docker/images.md` describes a future `pidgey` production image (`php:8.4-apache`, `apache2-foreground`), `deployment/pipeline.md` lists "Production hosting target" as a pending decision, and `deployment/production.md` assumes a reverse proxy in front of the app. `rewrite.md` must state the host it was verified against. Reconciling the deployment and docker specs needs its own issue; do not edit those files in #57.
- **`frontend/build.md` pending decision on `/admin/` serving.** `rewrite.md` shows that `DirectoryIndex` serves `/admin/` → `index.html` with no extra rule, on both dev and production. Step 02 only adds a link next to that pending decision; closing the decision is left to the product owner's judgment in a later issue.
- Keep the nginx line clearly marked as informative only. Pidgey runs on Apache.
