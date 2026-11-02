# Plan: Specs: static-file serving of the frontend at `/`

Issue: [121-specs-static-file-serving-of-the-frontend-at.md](../../issues/121-specs-static-file-serving-of-the-frontend-at.md)

## Overview

Docs-only change: update the specs so they describe the single-page application served at `/` by a PHP `StaticFileHandler` from `source/static/` (prefix matcher for `/assets/*`, extensionless GET fallback redirecting to `/#<path>`), replacing the Apache-served UI at `/admin/`. Owned entirely by the product-owner.

See [product-owner.md](product-owner.md) for the full plan.
