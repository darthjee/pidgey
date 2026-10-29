# Plan: RequestHandler base class and status handler

Issue: [74-requesthandler-base-class-and-status-handler.md](../../issues/74-requesthandler-base-class-and-status-handler.md)

## Overview

Build the first slice of `Pidgey\Http\`: minimal request and response models, the `Middleware` interface with `BaseMiddleware`, the `RequestHandler` template method, and `HealthHandler`. Then make `index.php` dispatch every request to `HealthHandler` and send the response through `ResponseSender`. Everything under `source/lib/` gets PHPUnit unit tests.

See [backend.md](backend.md) for the full plan.
