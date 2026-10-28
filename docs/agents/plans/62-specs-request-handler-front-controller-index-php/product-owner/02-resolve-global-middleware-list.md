# Resolve the global middleware list

## middlewares.md
- Remove the **Pending decision** "Where the global middleware list comes from"; `## Pending decisions` becomes `None.`
- Edge case 2: say `Router::add()` checks against the list passed to the `Router` constructor (link `routing.md#router`).
- `## Hand-offs` → #62 entry marked resolved, each item linking the matching section of `front-controller.md`.

## routing.md
- `## Router`: the constructor is `new Router(array $globalMiddlewares = [])` (class names); add `globalMiddlewares(): array`; the duplicate check in `add()` uses that list. Replace the "Pending decision recorded in middlewares.md" sentence.
- `config/routes.php` builds the router with `[OriginCheck::class]` (link `front-controller.md`).
- `## Hand-offs` → #62 entry marked resolved, linking `front-controller.md`.
- Testing: add "a duplicate of a global middleware throws `LogicException`".

## Files to Change
- `docs/agents/specs/request-handler/middlewares.md`
- `docs/agents/specs/request-handler/routing.md`
