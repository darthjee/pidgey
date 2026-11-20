# Plan: Enhance the Node client specs: runtime security and limits

Issue: [231-enhance-the-node-client-specs-runtime-security-and-limits.md](../../issues/231-enhance-the-node-client-specs-runtime-security-and-limits.md)

## Overview

This is a docs-only change to `docs/agents/specs/node-client/` that settles the four runtime
points #225 left open: cleartext `http://`, the retained response body, the stdin size cap,
and connection reuse with the worst-case duration. It also includes the `ABORTED` /
`possiblySent` correction. Every `**Pending decision** (#231)` marker and the hub's #231 row
are removed.

See [product-owner.md](product-owner.md) for the full plan.
