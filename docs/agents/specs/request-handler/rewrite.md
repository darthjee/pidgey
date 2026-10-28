# Apache Rewrite

Purpose: define how Apache rewrites requests to the front controller: the `.htaccess` rules, which paths bypass PHP, and the Apache requirements.

Part of the request flow indexed in [request-handler.md](../request-handler.md). This is the first link: Apache decides whether a request is served as a static file, rejected, or handed to `source/public/index.php` ([front-controller.md](front-controller.md)).

## `.htaccess`

`source/public/.htaccess` holds exactly these rules, in this order:

```apache
CGIPassAuth On
Options -Indexes
RewriteEngine On
RewriteRule (^|/)\. - [R=404,L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

Why each line is there:

- `CGIPassAuth On` — passes the `Authorization` header to PHP as `HTTP_AUTHORIZATION` (see [`Authorization` header](#authorization-header)).
- `Options -Indexes` — disables directory listings explicitly, without depending on the image or host default (see [Security](#security)).
- `RewriteEngine On` — enables `mod_rewrite` for the directory.
- `RewriteRule (^|/)\. - [R=404,L]` — answers a plain Apache 404 for any path segment starting with `.` (dotfiles). It comes before the front-controller rule so dotfiles never reach PHP (see [Security](#security)).
- `RewriteCond %{REQUEST_FILENAME} !-f` / `!-d` — existing files and directories are served by Apache and bypass PHP (see [Paths that bypass PHP](#paths-that-bypass-php)).
- `RewriteRule ^(.*)$ index.php [QSA,L]` — every other request goes to the front controller.
  - `QSA` keeps the query string.
  - `L` stops further rewriting.
- The rewrite is **internal**: `$_SERVER['REQUEST_URI']` keeps the original path and query, and the `Request` reads the route from there ([request-response.md](request-response.md)).

## Paths that bypass PHP

Unlike Tent, existing files and directories are not sent to PHP. Apache serves them directly:

- `/admin/` (the built UI's `index.html`, through `DirectoryIndex`);
- `/admin/assets/*` (the built UI's static assets);
- `/admin` → `/admin/`, a redirect produced by `mod_dir`.

`*.json` paths always reach PHP, anywhere in the tree, including under `/admin/`, because the UI never ships `.json` files. The routing rules live in [architecture/backend.md](../architecture/backend.md#entry-point); the built UI layout lives in [frontend/build.md](../frontend/build.md).

## `Authorization` header

By default Apache keeps the `Authorization` header out of the variables it passes to PHP:

- Under mod_php (dev), `$_SERVER['HTTP_AUTHORIZATION']` is null and only `getallheaders()` sees the header. Verified on `php:8.4-apache`.
- Under mod_fcgid (production), `HTTP_AUTHORIZATION` is null as well. Verified on the production host.

This silently breaks the `/v1` Bearer authentication ([api/authentication.md](../api/authentication.md)).

`CGIPassAuth On` (Apache 2.4.13+, needs the `AuthConfig` override) exposes the header as `HTTP_AUTHORIZATION`. A request with no `Authorization` header leaves it **unset**, not `""`. This behavior on both SAPIs is the decisive argument for `CGIPassAuth`.

Rejected alternatives:

- **`getallheaders()`** — ties the `Request` to the SAPI and is awkward to stub in tests.
- **`RewriteRule .* - [E=HTTP_AUTHORIZATION:...]`** — turns a missing header into `""` and adds a duplicate `REDIRECT_HTTP_AUTHORIZATION`.

Rule handed to [request-response.md](request-response.md): the `Request` reads headers **only** from `$_SERVER`.

## Apache requirements

Common to every environment:

- `mod_rewrite` is enabled.
- Apache 2.4.13 or later (for `CGIPassAuth`).
- The document root (`source/public/`) allows the override categories the rules need:
  - `FileInfo` — `mod_rewrite` directives;
  - `AuthConfig` — `CGIPassAuth`;
  - `Options` — `Options -Indexes`.

  `AllowOverride All` covers them. Any other image or host must grant at least these. A directive whose category is not allowed makes the **whole** `.htaccess` fail with a 500.
- Pidgey is **deployed at the root of its own domain**. When the app lives in a subfolder, `REQUEST_URI` and `SCRIPT_NAME` carry the folder prefix (observed on the production host). A base-path prefix is not supported.

### Dev

Met by `dockerfiles/dev_pidgey-base/Dockerfile` (`php:8.4-apache`, mod_php):

- `a2enmod rewrite`.
- The document root is `/home/app/app/public`, set through `APACHE_DOCUMENT_ROOT`.
- `AllowOverride All` comes from the image's `conf-available/docker-php.conf`. Its `<Directory /var/www/>` block is rewritten to the document root by the Dockerfile's `sed`, and it overrides the `AllowOverride None` in `apache2.conf` because `conf-enabled` is included later. The same file already sets `Options -Indexes`. Verified on `php:8.4-apache`.

### Production

The production host has the same setup as oak, a DreamHost shared host. Verified on 2026-10-27 with a temporary probe folder on oak, deleted afterwards:

- Ubuntu 24.04, Apache 2.4.58, PHP through **mod_fcgid** (`PHP_SAPI = cgi-fcgi`).
- The PHP version is chosen per domain in the panel. Production must use PHP 8.4 (the probe ran on 8.4.27).
- **There is no vhost access.** `.htaccess` is the only lever: every rule must live in `source/public/.htaccess`, and vhost-only directives (`AllowEncodedSlashes`, moving the rules into the vhost) are not available.
- Every directive in the `.htaccess` above is accepted, with no 500: `CGIPassAuth`, `Options -Indexes`, the dotfile rule and the front-controller rule.
- Results match dev:
  - non-file paths and `*.json` under `/admin/` reach `index.php` with the original `REQUEST_URI`;
  - `/admin` gets a 301 to `/admin/`;
  - static files are served.
- Without `CGIPassAuth On`, `HTTP_AUTHORIZATION` is null. With it, the token arrives, and a request with no header leaves it unset.

Other specs (`docker/images.md`, `deployment/`) still describe a different production target. This spec states the host the rules were verified against; reconciling those specs is out of scope here.

## Security

- **Dotfiles.** `/.env`, `/.git/config` and any path with a segment starting with `.` get a plain Apache 404 from `RewriteRule (^|/)\. - [R=404,L]`, placed before the front-controller rule. Without it they are served as plain text (verified: `/.env` → 200).
  - A 404, not a 403, so the response does not reveal that the file exists.
  - PHP never runs for scanner noise.
  - This blocks `/.well-known/` too. Nothing needs it today, because TLS is terminated outside the app. A future ACME setup must carve it out.
  - Rejected: routing dotfiles to `index.php` for a JSON 404. It is more consistent, but runs PHP for every probe.
- **Directory listing** is disabled explicitly with `Options -Indexes`. The dev image's `docker-php.conf` already sets it (verified: `/admin/assets/` → 403), but the rule must not depend on the image.
- **Only `index.php` runs.** `source/public/` holds only `index.php` and static assets. All PHP code lives outside the web root. Any other `.php` file placed in `public/` would execute (verified), so this is a layout rule stated here, not an `.htaccess` rule.

## Edge cases

Observed on `php:8.4-apache` with the rules above. They are documented behavior; no extra rules are added for them.

- **`/index.php`** reaches PHP with `REQUEST_URI=/index.php`. No route matches it, so it gets the 404 fallback ([routing.md](routing.md)).
- **`/admin/` on a fresh clone** (no frontend build) is not an existing directory, so it falls through to PHP and gets a JSON 404. See [frontend/build.md](../frontend/build.md).
- **Trailing slashes** (`/foo/`) are passed through unchanged. How they match is decided in [routing.md](routing.md).
- **Percent-encoding** (`/a%20b`) is passed through raw in `REQUEST_URI`. Decoding is the `Request`'s job, decided in [request-response.md](request-response.md).
- **Encoded slashes (`%2F`)** get an HTML 404 from Apache itself, before the rewrite runs, because of `AllowEncodedSlashes Off` (vhost-only, cannot be changed in `.htaccess`). Accepted: no route needs a `/` inside a `:param`. Path params never contain `/` ([routing.md](routing.md)), and these requests do not get the JSON error envelope.
- **`/.htaccess`** (and any `.ht*` file) gets a 403 from Apache's default config.
- **Errors produced by Apache itself** (dotfile 404, directory 403, `%2F` 404) go through the host's `ErrorDocument` handling. On oak these became 302 redirects to oak's own app, so the exact body and status on Pidgey's domain depend on the domain's error-page setup. The only requirement is that they never reach `index.php` and never expose the file.

## Performance

Note only:

- With `AllowOverride All`, Apache looks for `.htaccess` in every directory on each request.
- The `!-f` / `!-d` conditions cost one `stat` each.
- Both costs are negligible at Pidgey's scale. Moving the rules into the vhost (`AllowOverride None`) would avoid them, but the production host gives no vhost access, so this is not an option today.

## Transition

- Today there is no `.htaccess`. Only `/` and `/index.php` reach the placeholder `index.php`, which answers `{"status":"ok"}`. Every other dynamic path gets an Apache HTML 404.
- Nothing consumes the placeholder: no Docker `healthcheck`, CircleCI step or Makefile target calls it. No compatibility shim is needed.
- `/` stops being the health check. `GET /health.json` ([api/endpoints/operational.md](../api/endpoints/operational.md)) takes that role, with the same payload, once the front controller ([front-controller.md](front-controller.md)) lands. `/` is the document-root directory, so the rule does not rewrite it; it reaches `index.php` through `DirectoryIndex`, and the router decides what it returns.
- If the `.htaccess` ships before the real front controller, every non-file path answers 200 `{"status":"ok"}`. This only happens in dev, so the implementation issues may ship them together or in either order.
- The Vite dev proxy's `*.json` requests start reaching PHP instead of Apache's 404. That is the intended change.
- Updating the "stub `index.php`" wording in `architecture/repository-layout.md` belongs to the front-controller work ([front-controller.md](front-controller.md)), not here.

## Verification

Checks for the implementation issue:

- A non-file path reaches `index.php`.
- `/.env` (with a file present) returns 404, and a directory without an index returns 403.
- A request with `Authorization: Bearer <token>` exposes it in `$_SERVER['HTTP_AUTHORIZATION']`, and a request without one leaves it unset.

## nginx equivalent

Informative only; Pidgey runs on Apache: `try_files $uri $uri/ /index.php?$query_string`.
