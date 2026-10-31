# Known gaps
Create `docs/agents/deployment/known-gaps.md`:
- **No proxy rate limiting on the host.** Carry over the recommendation from `specs/deployment/production.md`: deployments behind a configurable reverse proxy should rate-limit `/auth/*.json` per client IP. The current host can't, so production keeps `USER_APPROVAL_REQUIRED=true`. App-level throttling is an open decision in `specs/api/pending.md`; link to it, without adding a second pending entry.
- **One DB user for migrations and the app.** The host supports a second user, and the split is tracked in #109.
- **No HSTS header** (tracked in #110).
- **Every path returns `200 {"status":"ok"}`** until the router exists (#111).
- **`TRUSTED_PROXY_HOPS=0` is unverified from outside** until an endpoint shows the client IP.
- **No `cleanup` action** until `darthjee/scripts:0.12.0`. Link to the operations runbook.

## Files to Change
- `docs/agents/deployment/known-gaps.md` — new.
