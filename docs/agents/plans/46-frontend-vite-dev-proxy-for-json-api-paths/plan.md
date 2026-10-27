# Plan: Frontend: Vite dev proxy for *.json API paths

Issue: [46-frontend-vite-dev-proxy-for-json-api-paths.md](../../issues/46-frontend-vite-dev-proxy-for-json-api-paths.md)

## Overview
Add a `'^/.*\\.json$'` proxy rule to the Vite dev server so every root-relative `*.json` API call reaches `http://app:80`, then update the spec notes that still describe this as follow-up #46.

See [frontend.md](frontend.md) for the full plan.
