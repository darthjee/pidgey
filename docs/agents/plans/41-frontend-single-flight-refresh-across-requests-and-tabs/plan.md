# Plan: Frontend: single-flight refresh across requests and tabs

Issue: [41-frontend-single-flight-refresh-across-requests-and-tabs.md](../../issues/41-frontend-single-flight-refresh-across-requests-and-tabs.md)

## Overview
Make `ApiClient` run a single refresh-token rotation at a time: one shared in-flight refresh per tab, guarded across tabs by a Web Locks API lock that re-reads the stored token, and delete the unused `AccountsClient.refresh` so `ApiClient` owns the only refresh path. Frontend-only change.

See [frontend.md](frontend.md) for the full plan.
