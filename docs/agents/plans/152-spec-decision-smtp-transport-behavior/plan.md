# Plan: Spec decision: SMTP transport behavior

Issue: [152-spec-decision-smtp-transport-behavior.md](../../issues/152-spec-decision-smtp-transport-behavior.md)

## Overview

Record the 13 SMTP transport decisions of #152 in the permanent specs and resolve every
open item of `implement-email/transport.md`. Documentation only, no code. The
product-owner updates `docs/agents/specs/`; the architect (coordinator) updates the
deployment docs, which are outside the product-owner's scope.

See [product-owner.md](product-owner.md) for the spec work.

## Architect step (deployment docs)

After the product-owner steps land, the architect edits `docs/agents/deployment/`:

- `server.md` — add the optional `.env` keys `SMTP_CONNECT_TIMEOUT` (10),
  `SMTP_COMMAND_TIMEOUT` (10), `SMTP_TOTAL_TIMEOUT` (25), `SMTP_CA_FILE` (empty),
  `SMTP_EHLO_NAME` (empty) to the variables table, with the operator rules:
  `SMTP_TOTAL_TIMEOUT` must stay below the Apache / proxy request timeout;
  `SMTP_EHLO_NAME` should be the host's public FQDN, ideally matching its reverse DNS;
  `SMTP_CA_FILE` replaces the system CA store (concatenate bundles to trust both).
- `variables.md` — list the new keys as `.env`-only (never CircleCI variables).
- `known-gaps.md` — DNS resolution is not bounded by `SMTP_CONNECT_TIMEOUT`; the total
  timeout vs. the (unverified) reverse-proxy timeout, next to the existing proxy gap.

## Files to Change

- `docs/agents/deployment/server.md`, `variables.md`, `known-gaps.md` (architect).
- Spec files: see [product-owner.md](product-owner.md).

## Notes

- App settings live only in the host's shared `.env` and are re-read on every request, so
  the new keys need no deploy-pipeline change.
- `architecture/dependencies.md` stays unchanged (Decision 10: no new runtime dependency).
- Where #151's password encryption service lives is not decided here.
