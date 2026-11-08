# Product-owner Plan: Spec decision: SMTP connection endpoint hardening

Main plan: [plan.md](plan.md)

## Overview

Write the decisions of #153 into the permanent specs and close
`implement-email/endpoints.md`. Each step names the files it touches; decisions refer to the
"Expected Behavior" section of the issue.

## Steps

- [01 — SSRF, DNS rebinding and the new failure code](product-owner/01-ssrf-and-host-check.md)
- [02 — Endpoint rules: redirection, deactivate, concurrency](product-owner/02-endpoint-rules.md)
- [03 — Test-email rate limit and recipient restriction](product-owner/03-test-email-limits.md)
- [04 — Data model: active index and test limits table](product-owner/04-data-model.md)
- [05 — Conventions, configuration and operator log](product-owner/05-conventions-config-log.md)
- [06 — Close implement-email/endpoints.md and pending summaries](product-owner/06-close-open-items.md)

## Notes

- Keep every message string exactly as written in the issue.
- Remove each matching "Pending decision" bullet where it is decided; leave the general API
  rate-limit and pagination items in `api/pending.md` open.
