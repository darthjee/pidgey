# Product Owner Plan: Decide the extra test checks for the clients

Issue: [220-decide-the-extra-test-checks-for-the-clients.md](../../issues/220-decide-the-extra-test-checks-for-the-clients.md)

## Overview

This plan records the decisions of #220 in the Node client specs:
- `testing.md`: the CI test infrastructure, the adopted extra checks (pack smoke test, `.d.ts` type check and source `checkJs`, CLI subprocess tests, coverage threshold, Node.js version matrix), the edge-case rules, the shared scripts, `Makefile` targets and CI jobs; the "No-hang expectation" settled by the subprocess spec;
- `packaging.md`: `c8` as the coverage tool, the pack check cross-referenced to the smoke test, the `clients/scripts/` ownership row;
- the hub `docs/agents/specs/node-client.md`: pending table.

No code, `Makefile` or CI changes: implementation belongs to later sub-issues of #219.

## Context

- The only #220 marker is `testing.md` "Extra checks" (`**Pending decision** (#220)`); `testing.md` "No-hang expectation" also points at #220 (left by #231).
- The hub's pending table has a #220 row (`docs/agents/specs/node-client.md:78`).
- `packaging.md` Stack says "nyc/c8"; its Ownership table has no row for `clients/scripts/`.
- The decisions, their trade-offs and the rejected alternatives are in the issue file's Solution section. Copy them faithfully, including the "Rejected" and "Not adopted" items and the accepted gaps; do not reopen them, and do not reopen the epic's decisions (in-process server with real `fetch`, Mailpit delivery assertions) or the #225/#231 decisions.

## Steps

- [01 — CI test infrastructure and edge-case rules](product-owner/01-ci-infrastructure-and-edge-cases.md)
- [02 — Extra checks](product-owner/02-extra-checks.md)
- [03 — Scripts, Makefile targets and CI jobs](product-owner/03-scripts-make-ci.md)
- [04 — Packaging, hub and consistency pass](product-owner/04-packaging-hub-consistency.md)

## Notes

- Script paths (`clients/scripts/checks.sh`, `clients/scripts/integration.sh`), the compose service name, the fixture and spec file names and the integration flag name (`PIDGEY_INTEGRATION=required`) are indicative in the issue: state them as examples, with the rule that the `Makefile` target and the CI job call the same script.
- The Node.js version numbers of the matrix come from #226; the CLI dependency form used by the smoke test comes from #222; the publish gate is #223. Reference those issues; leave their markers (#222, #223, #226) untouched.
- `docs/agents/clients/` (as-built docs) is not written here.
