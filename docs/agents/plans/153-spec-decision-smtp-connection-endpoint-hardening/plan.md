# Plan: Spec decision: SMTP connection endpoint hardening

Issue: [153-spec-decision-smtp-connection-endpoint-hardening.md](../../issues/153-spec-decision-smtp-connection-endpoint-hardening.md)

## Overview

Docs-only. Record the nine accepted decisions of #153 (SSRF and DNS rebinding, credential
redirection, test-email rate limit, `deactivate`, the "at most one active" index,
concurrency, the inconsistency fix, shared message templates, the operator log) in the
permanent specs, and close every open item of `docs/agents/specs/implement-email/endpoints.md`.
No code, migration or env file changes: those belong to the implementation issue of #149.

The spec work is owned by the product-owner — see [product-owner.md](product-owner.md).
The deployment docs are outside the product-owner's scope and are handled by the architect
in the step below, after the product-owner steps.

## Architect step — Deployment docs

- `docs/agents/deployment/server.md` — add `SMTP_ALLOW_PRIVATE_HOSTS` (production: `false`,
  the default; note that an internal relay requires `true` and reopens SSRF to internal
  addresses), `SMTP_TEST_MAX_ATTEMPTS`, `SMTP_TEST_WINDOW` and `LOG_LEVEL` to the shared
  `.env` keys, with defaults and operator rules.
- `docs/agents/deployment/variables.md` — list the four keys as `.env`-only app settings.
- `docs/agents/deployment/known-gaps.md` — record that ports are not restricted, so the
  test email still acts as a reachability oracle for public hosts and ports; and that the
  per-organization test lease does not bound the total number of workers across
  organizations.

## Notes

- `TOO_MANY_REQUESTS` is reserved in `api/conventions/errors.md` and becomes emitted by the
  test email only; the general API rate-limit item in `api/pending.md` stays open.
- Migration numbers: `0010` (generated column + unique index on `smtp_connections`) and
  `0011` (`smtp_test_limits`) are reserved in the specs; the implementation issue may
  renumber if another migration lands first.
- The development and test env files gain `SMTP_ALLOW_PRIVATE_HOSTS=true` in the
  implementation issue, not here.
