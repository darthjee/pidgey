# Issue: Raise the HSTS max-age to one day

## Description
Follow-up to #110, which ships `Strict-Transport-Security: max-age=300` in `source/public/.htaccess` as the first step of a staged rollout. With the 5-minute header verified on the production host, this is the next step: one day (`86400` = 60*60*24). A one-year value is left for a later step.

## Problem
A 300-second `max-age` only protects a browser for five minutes after its last visit, so a first request made over plain HTTP after that window can still be downgraded. Going straight to one year would mean that if HTTPS on the host broke (an expired cert or a host migration), anyone who had opened the admin UI would be locked out for up to a year. One day is a middle step: it gives real protection, and a lockout would last at most a day.

## Expected Behavior
- Precondition (met): #110 is merged, deployed and verified on the host.
- HTTPS responses (the API and `/admin/`, including its static files) carry `Strict-Transport-Security: max-age=86400`.
- Still no `includeSubDomains` and no `preload`; plain-HTTP responses (local docker, CI) still carry no header.

## Solution
- `source/public/.htaccess`: change `max-age=300` to `max-age=86400` and update the comment above it: the staged rollout is now at one day, with one year as a later step.
- `docs/agents/specs/deployment/security.md`: update the HSTS decision to the current value (#110, raised to one day in #115) and the staged-rollout bullet (one day caps any lockout after an HTTPS failure at 24 hours; one year comes later).
- `docs/agents/deployment/operations.md`: the release-verification step expects `max-age=86400`.
- `docs/agents/deployment/server.md`: state `max-age=86400`.
- Owner: infra.

## Benefits
Browsers stay on HTTPS for a day after each visit instead of five minutes, which closes most of the downgrade window while keeping any lockout after an HTTPS failure to at most 24 hours.
