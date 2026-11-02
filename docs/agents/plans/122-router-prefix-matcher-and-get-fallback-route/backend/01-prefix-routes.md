# Prefix routes in Route
A pattern ending in `/*` (e.g. `/assets/*`) is a **prefix route**.

**Matching:**
- It matches any path that starts with the literal part before `*` (`/assets/`), including nested `/`.
- `/assets` and `/assetsX` do not match.
- It follows the same method rule as every route, matched case-sensitively against the decoded `path()`.

**Compilation:**
- In `Route::compile()`, when the pattern ends with `/*`, compile the part before `*` as usual (literals quoted, `:param` groups allowed) and append `.*` instead of the closing anchor: `#^/assets/.*$#`.
- No param is captured for the rest; the handler reads it from `path()`.

**Validation (in the `Route` constructor):**
- A `*` anywhere other than as the final character after a `/` throws `LogicException`. Examples: `/a*`, `/a/*/b`, `/*x`.
- The message follows the existing style: `Route <METHOD> <pattern> ...`.

**Normalization:**
- `normalizedPattern()` keeps the `*`, so `GET /assets/*` registered twice is caught by the Router's existing duplicate check.
- `/assets/*` and `/assets/` are different patterns.

## Files to Change
- `source/lib/Http/Route.php` — prefix compilation, `*` placement validation, and docblocks (class doc mentions prefix routes; `regex()` example).
