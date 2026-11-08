# Product-Owner Plan: Spec decision: SMTP transport behavior

Main plan: [plan.md](plan.md)

## Context

The issue file holds the full decisions (Decisions 1–13, Backward compatibility); each step
below names the decisions it writes. Every "Pending decision" the steps resolve is removed
from its file. Fixed strings are API contract: copy them exactly from the issue.

## Steps

- [01 — Failure codes and error messages](product-owner/01-failures-and-errors.md)
- [02 — Send flow and SMTP connections](product-owner/02-send-flow-and-connections.md)
- [03 — MIME message](product-owner/03-mime-message.md)
- [04 — SMTP connection endpoint rules](product-owner/04-endpoint-field-rules.md)
- [05 — Configuration and backend layers](product-owner/05-configuration-and-backend.md)
- [06 — Implementation spec: transport](product-owner/06-implement-email-transport.md)

## CI Checks

- Documentation only; markdown links must resolve (no CI job runs on `docs/` beyond the
  usual pipeline).

## Notes

- Keep cross-links relative and anchors valid (`failures.md` ↔ `api/conventions/errors.md`,
  `send-flow.md` ↔ `configuration.md`).
- Do not touch `docs/agents/deployment/` (architect step in plan.md).
