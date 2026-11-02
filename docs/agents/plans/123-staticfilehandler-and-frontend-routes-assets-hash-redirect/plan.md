# Plan: StaticFileHandler and frontend routes (`/`, `/assets/*`, hash redirect)

Issue: [123-staticfilehandler-and-frontend-routes-assets-hash-redirect.md](../../issues/123-staticfilehandler-and-frontend-routes-assets-hash-redirect.md)

## Overview
Serve the built SPA at `/` through a PHP `StaticFileHandler` reading from `source/static/`. A `HashRedirectHandler` is the GET fallback. Both are wired in a new `routes/frontend.php`. All work is in `source/` (PHP).

See [backend.md](backend.md) for the full plan.
