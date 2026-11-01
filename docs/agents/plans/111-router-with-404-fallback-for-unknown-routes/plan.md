# Plan: Router with 404 fallback for unknown routes

Issue: [111-router-with-404-fallback-for-unknown-routes.md](../../issues/111-router-with-404-fallback-for-unknown-routes.md)

## Overview
Replace the placeholder front controller, which answers every path with the health handler. The new one routes requests through a `Router` built in `source/config/routes.php`. `GET /health.json` keeps answering `200 {"status":"ok"}`. Every other method and path gets the `404 NOT_FOUND` envelope (`Cannot <METHOD> <path>`), and any other error gets a `500` envelope. This is the minimal slice of the request-handler specs. CORS, `OriginCheck`, request logging, `HandlerFactory`/`Container` wiring and boot-time config validation stay out of scope. The new `source/config/` folder ships with every release, so the deployment docs and specs record that, and the known gap about every path returning 200 is closed.

## Agents involved

- [backend](backend.md)
- [infra](infra.md)
- [product-owner](product-owner.md)

## Shared contracts

- **New shipped path:** `source/config/` holds `routes.php` (returns the `Router`) and `routes/operational.php`. It is **not** in `.circleci/backend-exclude.txt`, so it ships with the release. The release's `link` step still adds `config/.env` (symlink) into that same folder. `config/` is outside `public/`, so it is not web-reachable.
- **Observable behaviour after release:** `GET /health.json` → `200 {"status":"ok"}`; any other method/path (including `/`, `HEAD`, `OPTIONS`) → `404` with `{"error":{"code":"NOT_FOUND","message":"Cannot <METHOD> <path>"},"statusCode":404,"timestamp":"…"}`.
- **Verification command** (for the deployment docs): `curl -s -o /dev/null -w '%{http_code}' https://<domain>/does-not-exist.json` → `404`.
