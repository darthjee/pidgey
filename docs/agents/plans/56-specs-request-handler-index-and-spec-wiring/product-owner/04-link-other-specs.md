# Link from overview/components.md and repository-layout.md

Add links only. Do not rewrite the wording that describes the current state.

- `docs/agents/specs/overview/components.md`, row "Apache + PHP backend" (currently "… through a single front controller … See [architecture/backend.md](../architecture/backend.md)."): add a link to `../request-handler.md` next to the `backend.md` link, e.g. "See [architecture/backend.md](../architecture/backend.md) and [request-handler.md](../request-handler.md)."
- `docs/agents/specs/architecture/repository-layout.md`, row `source/public/`: keep "Initially a stub `index.php` returning a JSON health response (no routing yet)." and add a sentence such as "The front controller is specified in [request-handler/front-controller.md](../request-handler/front-controller.md)."

## Files to Change
- `docs/agents/specs/overview/components.md` — add a link to the request-handler index.
- `docs/agents/specs/architecture/repository-layout.md` — add a link to the front-controller spec.
