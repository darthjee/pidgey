# RequireScope and RequireSendScope

Add the abstract middleware `Pidgey\Auth\RequireScope`, with a `protected const SCOPE`, and `final class RequireSendScope extends RequireScope { protected const SCOPE = 'send'; }`. This is the shape `request-handler/middlewares.md#scopes` fixes.

- `RequireScope` reads the attached token with `$request->require(<token Caller class>)`. Without `ApiTokenAuth` before it, the request is a misconfiguration and answers `500` through the `LogicException`.
- A token whose scopes do not contain `SCOPE` throws `ForbiddenException` and answers `403 FORBIDDEN`.

**Tests (unit):**
- a token with `send` passes;
- a token without it answers `403`;
- no attached token raises the `LogicException`.

## Files to Change
- `source/lib/Auth/RequireScope.php`: new, abstract.
- `source/lib/Auth/RequireSendScope.php`: new.
- `source/tests/unit/Auth/RequireScopeTest.php`: new.
