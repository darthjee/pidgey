# SetCookie and the access-token cookie
Implement `request-handler/request-response/response.md#setcookie` and the cookie of `api/authentication/access-token.md#cookie`. `setcookie()` is never used.

- `Pidgey\Http\SetCookie` (final readonly): name, value, `path`, `maxAge` (`?int`), `expires` (`?string`, an HTTP date), `httpOnly`, `secure`, `sameSite` (`?string`). The constructor rejects CR, LF, `;` and `,` in the name and the value (`InvalidArgumentException`, message without the value). `line(): string` returns `Set-Cookie: <name>=<value>; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=<n>[; Expires=<date>]` (attributes only when set, in that order). `applyTo(Response $response): void` (or have callers use `addHeader($cookie->line())`).
- `Pidgey\Auth\AccessTokenCookie`:
  - `NAME = 'access_token'`;
  - `set(string $jwt, int $ttl): SetCookie` → `access_token=<jwt>; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=<ttl>`;
  - `clear(): SetCookie` → `access_token=; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT`.
  - No `Domain`; the same attributes in every environment.
- Tests: exact set and clear lines; each rejected character in name and value; several cookies on one `Response` all survive in `headers()` (and are sent with `header($line, false)` by `ResponseSender`, already covered).

## Files to Change
- `source/lib/Http/SetCookie.php` — new.
- `source/lib/Auth/AccessTokenCookie.php` — new.
- `source/tests/unit/Http/SetCookieTest.php`, `source/tests/unit/Auth/AccessTokenCookieTest.php` — new.
