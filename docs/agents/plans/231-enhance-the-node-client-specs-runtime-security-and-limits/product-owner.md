# Product Owner Plan: Enhance the Node client specs: runtime security and limits

Issue: [231-enhance-the-node-client-specs-runtime-security-and-limits.md](../../issues/231-enhance-the-node-client-specs-runtime-security-and-limits.md)

## Overview

This plan records the decisions of #231 in the Node client specs:
- `library.md`: cleartext rule, keep-alive, worst-case duration;
- `errors-and-retry.md`: response body read cap, redaction and retention; the `ABORTED`
  correction;
- `cli.md`: `--allow-insecure-http`, stdin cap;
- `packaging.md`: breaking-change rows;
- `testing.md`: spec rows;
- the hub `docs/agents/specs/node-client.md`: pending table.

No code changes; implementation belongs to later sub-issues of #219.

## Context

- #225 (merged as #232) re-pointed four markers to #231:
  - `library.md:81` (cleartext);
  - `library.md:150` (connection reuse / duration);
  - `errors-and-retry.md:34` (retained body);
  - `cli.md:104` (stdin cap).
- The hub's pending table has a #231 row (`docs/agents/specs/node-client.md:79`).
- The decisions, their edge cases and the rejected alternatives are in the issue file's
  Solution section. Copy them faithfully: do not reopen them, and do not touch the #225
  decisions (supply chain, `--token-file`, OpenAPI route).
- API request size limits are tracked in #233 and stay out of this plan, except for the
  cross-reference.

## Steps

- [01 — Cleartext transport](product-owner/01-cleartext-transport.md)
- [02 — Retained response body and ABORTED correction](product-owner/02-response-body-and-aborted.md)
- [03 — Stdin cap, keep-alive and worst-case duration](product-owner/03-stdin-keepalive-duration.md)
- [04 — Breaking list, hub and consistency pass](product-owner/04-breaking-hub-consistency.md)

## Notes

- **Security review.** The issue asks the `security` agent to review the decisions. The
  reviewer should check the specs as written against the issue's Solution section: the
  loopback exemption, the redaction order, the non-enumerable fields, and `possiblySent` on
  an abort.
- **No-hang check.** The CLI "does not hang on an idle keep-alive socket" check needs
  subprocess tests, and those are still a pending decision of #220. Record the expectation
  in `testing.md`, pointing at #220 for how it runs; do not settle #220 here.
- Keep the other issues' markers (#220, #222, #223, #226) untouched.
