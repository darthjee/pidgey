# HealthHandler and index.php

Add the first concrete handler, and make the front controller use the new pipeline.

**`Pidgey\Http\Handler\HealthHandler`** (`final`, extends `RequestHandler`): `process()` returns `new JsonResponse(['status' => 'ok'])`. It has no constructor dependencies and touches nothing (no database, SMTP or files), as `docs/agents/specs/api/endpoints/operational.md` requires for `GET /health.json`.

**`source/public/index.php`**, which replaces the placeholder:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pidgey\Http\Handler\HealthHandler;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\ResponseSender;

// Until the router exists, every request is answered by HealthHandler.
$request = new ProcessingRequest(new Request());
$response = (new HealthHandler())->handle($request, []);
(new ResponseSender())->send($response);
```

Check the autoload path against the container layout: the dev image mounts `source/` at `/home/app/app`, and the document root is `/home/app/app/public`. Make sure `composer lint` passes on `public/`; phpcs `PSR1.Files.SideEffects` may need the `use` statements placed before `require`. Follow whatever phpcs accepts.

Tests:

- `Handler/HealthHandlerTest`:
  - `handle(new ProcessingRequest(new Request(['method' => $m, 'uri' => $uri])), [])` returns status 200, the `Content-Type: application/json` line and the body `{"status":"ok"}`;
  - use a data provider over several methods (`GET`, `POST`, `HEAD`, `OPTIONS`, `DELETE`) and paths (`/`, `/health.json`, `/anything/else?x=1`).

Manual check (done by the user): `docker compose up app`, then `curl -i http://localhost:8080/` and `curl -i -X POST http://localhost:8080/foo/bar` both answer `200` with `{"status":"ok"}`.

## Files to Change

- `source/lib/Http/Handler/HealthHandler.php` — new.
- `source/tests/unit/Http/Handler/HealthHandlerTest.php` — new.
- `source/public/index.php` — replace the placeholder with the dispatch above.
