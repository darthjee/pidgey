# Write rewrite.md

Replace the stub `docs/agents/specs/request-handler/rewrite.md` with the full spec. Keep the existing title (`# Apache Rewrite`) and the `Purpose:` line from #56 exactly. Remove the `**Pending decision:** content to be specified in #57 ...` stub marker.

Source of truth for the content: the issue's **Solution** section. Turn its bullet groups into sections, for example:

1. **`.htaccess`** — the exact file content as a code block, in this order:
   ```apache
   CGIPassAuth On
   Options -Indexes
   RewriteEngine On
   RewriteRule (^|/)\. - [R=404,L]
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^(.*)$ index.php [QSA,L]
   ```
   followed by one line per directive saying why it is there (flags `QSA`/`L`, internal rewrite keeping `REQUEST_URI`).
2. **Paths that bypass PHP** — existing files and directories (unlike Tent): `/admin/`, `/admin/assets/*`, the `mod_dir` redirect `/admin` → `/admin/`; `*.json` always reaches PHP. Link `../architecture/backend.md#entry-point` and `../frontend/build.md`.
3. **`Authorization` header** — `CGIPassAuth On`, why (hidden from PHP under both mod_php and mod_fcgid), the rejected alternatives, and the rule handed to `request-response.md`: the `Request` reads headers only from `$_SERVER`.
4. **Apache requirements** — `mod_rewrite`; override categories `FileInfo`, `AuthConfig`, `Options` (a disallowed category makes the whole `.htaccess` a 500); Apache 2.4.13+ for `CGIPassAuth`; deployed at the domain root (no base-path prefix).
   - **Dev** (`dockerfiles/dev_pidgey-base/Dockerfile`, `php:8.4-apache`, mod_php): `a2enmod rewrite`, `APACHE_DOCUMENT_ROOT=/home/app/app/public`, `AllowOverride All` + `Options -Indexes` from the image's `docker-php.conf`.
   - **Production** (DreamHost shared host, same setup as oak): Apache 2.4.58, PHP 8.4 via mod_fcgid selected per domain, no vhost access, so `.htaccess` is the only lever and vhost-only directives are unavailable. Verified on 2026-10-27.
5. **Security** — dotfile rule (404 not 403; blocks `/.well-known/` too, so a future ACME setup must carve it out), explicit `Options -Indexes`, and the layout rule that `source/public/` holds only `index.php` and static assets.
6. **Edge cases** — `/index.php`, `/admin/` on a fresh clone, trailing slashes (→ `routing.md`), percent-encoding (→ `request-response.md`), `%2F` (Apache 404; path params never contain `/`), `.ht*` files (403), and Apache-generated errors going through the host's `ErrorDocument` handling (never reach `index.php`, never expose the file).
7. **Performance** — note only, as in the issue.
8. **Transition** — the placeholder `index.php`, `/` vs `GET /health.json`, shipping order, Vite proxy; link `../api/endpoints/operational.md`.
9. **Verification (for the implementation issue)** — the three checks from the issue.
10. **nginx equivalent** — one informative line.

Use relative links to sibling specs (`request-response.md`, `routing.md`, `front-controller.md`) where the issue hands work off. No **Pending decision** markers remain unless one is genuinely open (none is expected).

## Files to Change
- `docs/agents/specs/request-handler/rewrite.md` — replace the stub with the full spec.
