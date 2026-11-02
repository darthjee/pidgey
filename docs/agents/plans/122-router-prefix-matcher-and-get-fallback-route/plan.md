# Plan: Router: prefix matcher and GET fallback route

Issue: [122-router-prefix-matcher-and-get-fallback-route.md](../../issues/122-router-prefix-matcher-and-get-fallback-route.md)

## Overview
Add prefix routes (trailing `/*`) to `Route` and a single GET fallback (`Router::fallback()`) to `Router`. Both are backend-only changes under `source/lib/Http/`, with unit tests. No frontend route is registered.

See [backend.md](backend.md) for the full plan.
