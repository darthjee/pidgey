# OriginCheck middleware and registration

Add `Pidgey\Http\Middleware\OriginCheck`. Before creating it, check `specs/request-handler/middlewares.md` for the namespace; otherwise use `Pidgey\Http\OriginCheck`, next to `BaseMiddleware`. It extends `BaseMiddleware`.

## Constructor

`__construct(?TrustedOrigins $trusted = null)`, which defaults to `TrustedOrigins::fromEnv()`. `FrontController` calls `new $class()`.

## `processRequest()`

1. **Safe methods:** `GET`, `HEAD`, `OPTIONS` → return.
2. **`Sec-Fetch-Site` present:**
   - allowed when it is exactly `same-origin` or `none`, or when `Origin` is present and `$trusted->contains($origin)`;
   - otherwise throw.
3. **Neither header:** return.
4. **Only `Origin` present:**
   - allowed when it is trusted;
   - otherwise parse `Origin` and `Host`, and allow when the hosts are equal ignoring case and the ports are equal;
   - otherwise throw. An unparseable `Origin` or `Host`, and `null`, all throw.

Throw `new ForbiddenException('Cross-site request rejected')`.

## Parsing helpers

Private, without relying on `parse_url`'s leniency for the `Origin`:

- **`Origin`:** match `^(https?)://(\[[0-9A-Fa-f:.]+\]|[^\s/?#@:\[\]]+)(?::([0-9]{1,5}))?$`, then reject port `0` and ports above 65535.
- **`Host`:** match `^(\[[0-9A-Fa-f:.]+\]|[^\s/?#@:\[\]]+)(?::([0-9]{1,5}))?$`.

## Registration

In `source/config/routes.php`:

- `$router = new Router([OriginCheck::class]);`
- add the `use` line;
- remove the "empty until OriginCheck lands" comment.

## Tests

- **Unit (`OriginCheckTest`):**
  - safe methods pass even with a foreign `Origin`;
  - `POST`, `PUT`, `PATCH`, `DELETE` and an unknown method (`PROPFIND`) are checked;
  - `Sec-Fetch-Site` `same-origin`/`none` pass, `cross-site`/`same-site` with an untrusted origin throw, `cross-site` with a trusted origin passes, uppercase `Same-Origin` throws;
  - no headers pass;
  - `Origin` only: trusted, same host and port, default ports (`https://h` vs `Host: h`, `http://h` vs `Host: h:80`), port mismatch, host case difference, IPv6 (`http://[::1]:8080` vs `Host: [::1]:8080`), `null`, user info (`https://a@h`), trailing `/`, `ftp://h`, missing `Host`.
- **Routes (`source/tests/unit/Config/RoutesTest.php`):** `globalMiddlewares()` is `[OriginCheck::class]`.
- **Integration (`FrontControllerTest`):** a `POST` with `Origin: https://evil.example` and `Host: pidgey.example` gets `403` with the `FORBIDDEN` envelope, and a `GET` with the same headers is unaffected. Use a test router with a `POST` route if no real `POST` route exists yet.

## Files to Change

- `source/lib/Http/OriginCheck.php` (or `Middleware/OriginCheck.php`, per spec): new.
- `source/config/routes.php`: register the global middleware.
- `source/tests/unit/Http/OriginCheckTest.php`: new.
- `source/tests/unit/Config/RoutesTest.php`
- `source/tests/unit/Http/FrontControllerTest.php`
