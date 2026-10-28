# Plan: Route every request to index.php

Issue: [73-route-every-request-to-index-php.md](../../issues/73-route-every-request-to-index-php.md)

## Overview

Add `source/public/.htaccess` exactly as specified in `docs/agents/specs/request-handler/rewrite.md`, so every non-file request reaches `index.php`. No Docker image change is needed. CI failures do not block the PR; instead, one fix issue is opened per broken CI area.

See [infra.md](infra.md) for the full plan.
