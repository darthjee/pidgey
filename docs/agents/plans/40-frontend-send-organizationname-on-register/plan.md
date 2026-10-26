# Plan: Frontend: send organizationName on register

Issue: [40-frontend-send-organizationname-on-register.md](../../issues/40-frontend-send-organizationname-on-register.md)

## Overview
Add a required "Organization name" field to the login modal's Register mode, validate it client-side (required after trimming, max 255 characters), send it as `organizationName` from `AccountsClient.register`, and delete the orphaned `RegisterController`. Frontend-only; the API contract is unchanged.

See [frontend.md](frontend.md) for the full plan.
