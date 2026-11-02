# 02 — StaticFileHandler

Create `final class StaticFileHandler extends RequestHandler` in `Pidgey\Http\Handler`.

## Constructor
`__construct(?string $baseDir = null, ?callable $logger = null)`:
- `$baseDir` defaults to `dirname(__DIR__, 3) . '/static'`, which is `source/static`. Check the depth: the class lives in `source/lib/Http/Handler/`.
- `$logger` defaults to `static fn (string $line) => error_log($line)`, stored as a `Closure`, as in `ErrorFormatter`.

## process()
1. Map the path from `$request->path()` (decoded):
   - `/` → `index.html`, with the index headers;
   - anything else (only `/assets/*` routes here) → the path minus its leading `/`, with the asset cache header.
2. Reject with a 404 when:
   - the path contains `\`;
   - any segment equals `..`;
   - any segment starts with `.`.

   Split on `/` and ignore empty segments.
3. Build `$candidate = $baseDir . '/' . $relative` and resolve it:
   - `realpath($candidate)`; a `false` result is a 404;
   - `realpath($baseDir)`; when the base folder itself is missing (fresh clone), this is also a 404;
   - the resolved file must start with `$realBase . DIRECTORY_SEPARATOR`, otherwise 404 (symlink escape);
   - `is_file()` must be true, otherwise 404 (directory).
4. The extension must be on the allow-list, matched with `strtolower(pathinfo(..., PATHINFO_EXTENSION))`, otherwise 404. Keep the allow-list as a `private const` map:

   | Extension | Content-Type |
   | --- | --- |
   | `html` | `text/html; charset=utf-8` |
   | `js` | `text/javascript; charset=utf-8` |
   | `css` | `text/css; charset=utf-8` |
   | `svg` | `image/svg+xml` |
   | `png` | `image/png` |
   | `ico` | `image/x-icon` |
   | `woff` | `font/woff` |
   | `woff2` | `font/woff2` |

5. Read the file whole with `file_get_contents`; a `false` result is a 404. Return a `Response`, status 200, with these headers in order:
   - `Content-Type`;
   - `Content-Length` (`strlen` of the body);
   - `X-Content-Type-Options: nosniff`;
   - for the index: `Cache-Control: no-cache`, `Content-Security-Policy: frame-ancestors 'none'`, `X-Frame-Options: DENY`;
   - for assets: `Cache-Control: public, max-age=31536000, immutable`.

- Every 404 goes through one private helper:
  - it calls `($this->logger)` with a line naming the resolved candidate path, e.g. `StaticFileHandler: not served: <candidate>`;
  - it throws `new NotFoundException('Cannot GET ' . $request->path())`;
  - the message must equal the router's message exactly, and the filesystem path never reaches the client.
- No `Container`, no database, no middlewares.
- Keep each method small enough for the complexity/lint rules in `composer lint`.

## Tests
- Add `source/tests/unit/Http/Handler/StaticFileHandlerTest.php`, using a fixture folder at `source/tests/fixtures/static/` that holds:
  - `index.html`;
  - `assets/app.js`, `assets/app.css`, `assets/font.woff` and `assets/font.woff2`;
  - a nested `assets/dir/` containing `.gitkeep` or a file;
  - `assets/.hidden.js`;
  - `assets/notes.txt`;
  - `secret.html` at the fixture root, which is not reachable through `/assets/` traversal.
- For the symlink escape, create the symlink in `setUp()` under a temp copy or `sys_get_temp_dir()`, pointing outside the base folder, and remove it in `tearDown()`. Do not commit symlinks.
- Cases:
  - `/` gives 200 with every index header and the exact body;
  - `/assets/app.js` and `/assets/app.css` give the right types, `Content-Length` and the immutable cache header;
  - the `woff` and `woff2` fonts are served;
  - an upper-case extension (`APP.JS` fixture or a case variant) is served;
  - each of these throws `NotFoundException` with the message `Cannot GET <path>`, and the recording logger gets exactly one line containing the resolved path:
    - a missing file;
    - a directory;
    - `/assets/../secret.html`;
    - `/assets/a\b.js`;
    - `/assets/.hidden.js`;
    - the symlink escape;
    - `/assets/notes.txt`;
  - a base folder without `index.html` (fresh clone) throws on `/`;
  - the default constructor runs without throwing, which keeps coverage of the defaults.

## Files to Change
- `source/lib/Http/Handler/StaticFileHandler.php` — new.
- `source/tests/unit/Http/Handler/StaticFileHandlerTest.php` — new.
- `source/tests/fixtures/static/**` — new fixture files.
