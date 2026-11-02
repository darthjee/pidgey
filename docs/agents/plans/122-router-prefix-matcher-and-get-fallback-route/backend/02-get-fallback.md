# GET fallback in Router
Add `Router::fallback(string $handlerClass): void`.

**Registration:**
- Throws `LogicException` when `$handlerClass` is not a `RequestHandler` subclass (same check and message style as `add()`).
- Throws `LogicException` when a fallback is already registered.
- Store it in its own nullable property (e.g. a `?Route` built as `new Route('GET', '*', $handlerClass)` with a fixed pattern, or a dedicated private field holding the class name), **not** in `$routes`. It is therefore always tried last, whatever the registration order.
- No route middlewares; the global middlewares still apply through the dispatcher as usual.

**Matching (`match()`):**
1. Try the ordinary routes as today, first match wins.
2. If none matched and a fallback is registered, apply the fixed rule (a private method such as `appliesToFallback(Request)`). All must hold:
   - `$request->method() === 'GET'` (so `HEAD` is rejected);
   - the last path segment (text after the final `/`) contains no `.`; `/foo/` has an empty last segment and qualifies;
   - the path does not start with `/v1/`. `/v1` itself qualifies, which is accepted per the issue.
3. When the rule holds, return a `RouteMatch` with the fallback handler class, `[]` middlewares and `[]` params. Use the request's `path()` as `pattern`, or a fixed marker such as `'*'`, and document the choice in the docblock.
4. Otherwise return `null` (the dispatcher's 404 is unchanged).

Update the class docblock: first match wins, then the GET fallback, then `null`.

## Files to Change
- `source/lib/Http/Router.php` — `fallback()`, fallback storage, the rule, and the `match()` extension with docblocks.
