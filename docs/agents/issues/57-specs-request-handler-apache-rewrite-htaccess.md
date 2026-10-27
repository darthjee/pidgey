# Issue: Specs: request-handler — Apache rewrite (.htaccess)

## Description

Part of Epic #55. Write `docs/agents/specs/request-handler/rewrite.md`, the first link in the request flow: how Apache rewrites incoming requests to the front controller `source/public/index.php`. The file currently exists only as a stub created by #56.

Reference: `/Users/darthjee/messages/php-request-handler/rewrite.md` (how Tent does it).

The Epic specifies how HTTP requests flow through the Pidgey backend:

```
Apache + .htaccess → source/public/index.php → Request → routing → middlewares (pre hooks) → request handler → middlewares (post hooks) → Response → ResponseSender
```

The specs live under `docs/agents/specs/request-handler/` and are indexed by `docs/agents/specs/request-handler.md`.

**Rules for every part:**
- **Spec only.** No PHP, `.htaccess` or tests are written here. Implementation issues come later.
- **Structure only.** What each endpoint does stays in `docs/agents/specs/api/endpoints/`.
- **Format:** follow the conventions in `docs/agents/specs.md`. The file starts with a title and a one-line `Purpose:` statement, open questions are marked **Pending decision**, and everything is in English.

**Decisions already made in #55:**
- **Hand-written.** Classes live in `Pidgey\Http\`. No router or middleware library, and no new runtime dependency (`architecture/dependencies.md`).
- **Routing.** Routes are registered dev-API style (method + path with `:param` → handler class). They are ordered, the first match wins, and there is a guaranteed 404 fallback. The router captures the `:param` values and passes them to the handler.
- **Handlers.** Tent-style base class (a `final` entry method calls an abstract processing method). A new instance is built per request through a factory that injects its dependencies.
- **Middlewares.** Tent-style pre/post hooks (`processRequest` / `processResponse`).
  - A request hook can answer early by setting a response on the processing request.
  - Middlewares are attached globally and per route.
  - Their order follows the guard order in `api/conventions/format.md`.
- **Error handling.** Exceptions are turned into the JSON error envelope around the whole dispatch, not by a middleware.

## Problem

Today `source/public/` has no `.htaccess`. Only `/` and `/index.php` reach PHP, and every API path gets an Apache HTML 404. The rewrite also has non-obvious traps, all verified in the `php:8.4-apache` dev image and on the production host (oak, DreamHost):

- Apache hides the `Authorization` header from PHP, which silently breaks `/v1` Bearer authentication.
- Dotfiles in the web root are served as plain text.
- Dev (mod_php, full vhost control) and production (mod_fcgid, `.htaccess` only) differ.

None of this is written down yet.

## Expected Behavior

`docs/agents/specs/request-handler/rewrite.md` replaces the stub. It:

- starts with a title and the `Purpose:` line settled in #56;
- specifies the exact `.htaccess` content and why each rule is there;
- lists the Apache requirements for dev and production;
- documents the edge cases, security rules, transition and verification notes below.

Nothing outside the spec changes: no `.htaccess`, PHP or Dockerfile is written here. The flow diagram and parts table in `request-handler.md` stay correct (update them only if this spec changes the flow).

## Solution

Specify the following in `rewrite.md`:

- **`.htaccess` in `source/public/`:**
  - `CGIPassAuth On`
  - `Options -Indexes`
  - `RewriteEngine On`
  - `RewriteRule (^|/)\. - [R=404,L]`
  - `RewriteCond %{REQUEST_FILENAME} !-f`
  - `RewriteCond %{REQUEST_FILENAME} !-d`
  - `RewriteRule ^(.*)$ index.php [QSA,L]`
- **Why existing files and directories bypass PHP** (unlike Tent): `/admin/`, `/admin/assets/*` and the `/admin` → `/admin/` redirect from `mod_dir` are served by Apache. `*.json` paths reach PHP because the UI never ships `.json` files. See `architecture/backend.md` and `frontend/build.md`.
- **Flags:** `QSA` keeps the query string. `L` stops further rewriting.
- **Internal rewrite:** `$_SERVER['REQUEST_URI']` keeps the original path and query, and the `Request` reads the route from there.
- **Apache requirements, as met by `dockerfiles/dev_pidgey-base/Dockerfile`:**
  - `a2enmod rewrite`.
  - The document root is `/home/app/app/public`, set through `APACHE_DOCUMENT_ROOT`.
  - `AllowOverride All` comes from the image's `conf-available/docker-php.conf`. Its `<Directory /var/www/>` block is rewritten to the document root by the Dockerfile's `sed`, and it overrides the `AllowOverride None` in `apache2.conf` because `conf-enabled` is included later. Verified on `php:8.4-apache`.
  - **Override requirement:** the document root must allow the override categories the rules need: `FileInfo` (mod_rewrite), `AuthConfig` (`CGIPassAuth`) and `Options` (`Options -Indexes`). `AllowOverride All` covers them. Any other image or host must grant at least these. A directive whose category is not allowed makes the whole `.htaccess` fail with a 500.
- **Production host (same setup as oak, a DreamHost shared host).** Verified on 2026-10-27 with a temporary probe folder on oak, deleted afterwards:
  - Ubuntu 24.04, Apache 2.4.58, PHP through **mod_fcgid** (`PHP_SAPI = cgi-fcgi`). The PHP version is chosen per domain in the panel, and production must use 8.4. The probe ran on 8.4.27.
  - **There is no vhost access.** `.htaccess` is the only lever, so every rule must live in `source/public/.htaccess`, and vhost-only directives (`AllowEncodedSlashes`, moving the rules into the vhost) are not available.
  - Every directive above is accepted, with no 500: `CGIPassAuth`, `Options -Indexes`, the dotfile rule and the front-controller rule.
  - Results matched dev: non-file paths and `*.json` under `/admin/` reach `index.php` with the original `REQUEST_URI`. `/admin` gets a 301 to `/admin/`. Static files are served.
  - **Without `CGIPassAuth On`, `HTTP_AUTHORIZATION` is null under fcgid as well.** With it, the token arrives, and a request with no header leaves it unset. This is the decisive argument for `CGIPassAuth` over `getallheaders()`.
  - **Error statuses produced by Apache itself** (dotfile 404, directory 403, `%2F` 404) go through the host's `ErrorDocument` handling. On oak these became 302 redirects to oak's own app, so the exact body and status on Pidgey's domain depend on the domain's error-page setup. The spec only requires that they never reach `index.php` and never expose the file.
  - **Deployed at the domain root.** When the app lives in a subfolder, `REQUEST_URI` and `SCRIPT_NAME` carry the folder prefix (observed in the probe). Pidgey is served from the root of its own domain, and a base-path prefix is not supported.
- **`Authorization` header passthrough (`CGIPassAuth On`):** by default Apache keeps the `Authorization` header out of the variables it passes to PHP. Under mod_php, `$_SERVER['HTTP_AUTHORIZATION']` is null and only `getallheaders()` sees it. That silently breaks the `/v1` Bearer authentication. `CGIPassAuth On` (Apache 2.4.13+, needs the `AuthConfig` override) exposes it as `HTTP_AUTHORIZATION`, and a missing header stays unset, not `""`. Verified on `php:8.4-apache`.
  - Rejected: reading headers with `getallheaders()`, which ties the `Request` to the SAPI and is awkward to stub. Also rejected: the `RewriteRule .* - [E=HTTP_AUTHORIZATION:...]` trick, which turns a missing header into `""` and adds a duplicate `REDIRECT_HTTP_AUTHORIZATION`.
  - Consequence for #58 (`request-response.md`): the `Request` reads headers only from `$_SERVER`.
- **Edge cases** (observed on `php:8.4-apache` with the rules above; document them, no extra rules):
  - **`/index.php`** reaches PHP with `REQUEST_URI=/index.php`. No route matches it, so it gets the 404 fallback.
  - **`/admin/` on a fresh clone** (no frontend build) is not an existing directory, so it falls through to PHP and gets a JSON 404. Cross-reference `frontend/build.md`.
  - **Trailing slashes** (`/foo/`) are passed through unchanged. How they match is decided in #59 (`routing.md`).
  - **Percent-encoding** (`/a%20b`) is passed through raw in `REQUEST_URI`. Decoding is the `Request`'s job, decided in #58 (`request-response.md`).
  - **Encoded slashes (`%2F`)** get an HTML 404 from Apache itself, before the rewrite runs, because of `AllowEncodedSlashes Off` (vhost-only, cannot be changed in `.htaccess`). Accepted: no route needs a `/` inside a `:param`. The spec states that path params never contain `/`, and that these requests do not get the JSON error envelope.
  - **`/.htaccess`** (and any `.ht*` file) gets a 403 from Apache's default config.
- **Security:**
  - **Dotfiles** (`/.env`, `/.git/config`, any path segment starting with `.`) get a plain Apache 404 from `RewriteRule (^|/)\. - [R=404,L]`, placed before the front-controller rule. Without it, they are served as plain text (verified: `/.env` → 200). A 404, not a 403, so the response doesn't reveal that the file exists, and PHP never runs for scanner noise. This blocks `/.well-known/` too. Nothing needs it today, because TLS is terminated outside the app. A future ACME setup must carve it out.
    - Rejected: routing dotfiles to `index.php` for a JSON 404. It's more consistent, but runs PHP for every probe.
  - **Directory listing** is disabled explicitly with `Options -Indexes`. The image's `docker-php.conf` already sets it (verified: `/admin/assets/` → 403), but the rule must not depend on the image.
  - **Only `index.php` runs.** `source/public/` holds only `index.php` and static assets. All PHP code lives outside the web root. Any other `.php` file placed in `public/` would execute (verified), so this is a layout rule stated in the spec, not an `.htaccess` rule.
- **Performance (note only):**
  - With `AllowOverride All`, Apache looks for `.htaccess` in every directory on each request.
  - The `!-f` / `!-d` conditions cost one `stat` each.
  - Both costs are negligible at Pidgey's scale. Moving the rules into the vhost (`AllowOverride None`) would avoid them, but the production host gives no vhost access, so this is not an option today.
- **Transition (backward compatibility):**
  - Today there is no `.htaccess`. Only `/` and `/index.php` reach the placeholder `index.php`, which answers `{"status":"ok"}`. Every other dynamic path gets an Apache HTML 404.
  - Nothing consumes the placeholder: no Docker `healthcheck`, CircleCI step or Makefile target calls it. No compatibility shim is needed.
  - `/` stops being the health check. `GET /health.json` (`api/endpoints/operational.md`) takes that role, with the same payload, once the front controller (#62) lands. `/` is the document-root directory, so the rule does not rewrite it and it reaches `index.php` through `DirectoryIndex`. The router then decides what it returns.
  - If the `.htaccess` ships before the real front controller, every non-file path answers 200 `{"status":"ok"}`. This only happens in dev, so the implementation issues may ship them together or in either order.
  - The Vite dev proxy's `*.json` requests start reaching PHP instead of Apache's 404. That is the intended change.
  - Updating the "stub `index.php`" wording in `architecture/repository-layout.md` belongs to #62, not here.
- **Verification notes for implementation:**
  - A non-file path must reach `index.php`.
  - `/.env` (with a file present) must return 404, and a directory without an index must return 403.
  - A request with `Authorization: Bearer <token>` must expose it in `$_SERVER['HTTP_AUTHORIZATION']`, and a request without one must leave it unset.
- **nginx equivalent (informative only):** `try_files $uri $uri/ /index.php?$query_string`.

### Hand-offs to other parts of #55

- #58 (`request-response.md`): the `Request` reads headers only from `$_SERVER`, and it decodes the raw path from `REQUEST_URI`.
- #59 (`routing.md`): trailing-slash matching; path params never contain `/`.
- #62 (`front-controller.md`): replaces the placeholder `index.php` and updates the "stub" wording in `architecture/repository-layout.md`.

## Benefits

- The `.htaccess` implementation issue can be written straight from the spec, with every directive justified and already verified on both dev and production.
- `/v1` authentication works on the real host from the first deploy.
- Dotfiles and directory listings are closed off before any code ships.
