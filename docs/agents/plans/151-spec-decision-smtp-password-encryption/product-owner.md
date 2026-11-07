# Plan: Spec decision: SMTP password encryption

Issue: [151-spec-decision-smtp-password-encryption.md](../../issues/151-spec-decision-smtp-password-encryption.md)

## Overview

Write every "Decided" section of issue #151 into the permanent specs, then mark
`implement-email/encryption.md` resolved. The issue file is the source of the exact values
(HKDF `info` strings, byte layout, associated data, failure names, error code and message,
edge cases); copy them verbatim, do not reinterpret.

## Context

- Hub/index/detail granularity (`docs/agents/specs.md`): a new detail file is indexed in its
  area index (`email.md`).
- Permanent specs are the source of truth; `implement-email/*` are temporary (epic #149) and
  link to the permanent specs instead of restating them.
- Key rules reuse the `SECRET_KEY` wording in `architecture/configuration.md#secret-keys`
  (raw string, ≥32 bytes, no `,`, development key by exact comparison).
- Deployment docs (`docs/agents/deployment/`) are **not** in this agent's scope; the
  coordinator edits them (see [plan.md](plan.md)). Where a spec needs them, link
  `deployment/operations.md` / `server.md`.
- App settings live only in the host's shared `.env`, never in CircleCI variables; a key
  change needs no deploy. Do not write "CircleCI deploy variables" or a deploy step.

## Steps

- [01 — New `email/password-encryption.md`](product-owner/01-password-encryption-spec.md)
- [02 — SMTP connections and failures](product-owner/02-connections-and-failures.md)
- [03 — Configuration and dependencies](product-owner/03-configuration-and-dependencies.md)
- [04 — Data model and endpoints](product-owner/04-data-model-and-endpoints.md)
- [05 — Resolve `implement-email/encryption.md`](product-owner/05-implement-email.md)

## Notes

- No source, Docker, CI or root file changes.
- Every Pending decision about encryption disappears from `email/smtp-connections.md` and
  `implement-email/encryption.md`; the other Pending decisions there stay.
