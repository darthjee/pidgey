# Index the area in specs.md

In the `## Index` table of `docs/agents/specs.md`, add this row **directly after the `architecture` row** (the new area details the backend's HTTP layer):

```markdown
| [request-handler](specs/request-handler.md) | How HTTP requests flow through the backend: Apache rewrite, front controller, request/response, routing, request handlers, middlewares. |
```

Also, if the intro paragraph of `specs.md` lists the areas, mention request handling there.

## Files to Change
- `docs/agents/specs.md` — new Index row after `architecture`.
