# Issue: Specs: request-handler index and spec wiring

## Description

Create the index of the request-handler spec area and fit it into the existing specs.

Part of Epic #55: specify how HTTP requests flow through the Pidgey backend.

```
Apache + .htaccess → source/public/index.php → Request → routing → middlewares (pre hooks) → request handler → middlewares (post hooks) → Response → ResponseSender
```

The specs live under `docs/agents/specs/request-handler/` and are indexed by `docs/agents/specs/request-handler.md`.

**Rules for every part:**
- **Spec only.** No PHP, `.htaccess` or tests are written here. Implementation issues come later.
- **Structure only.** What each endpoint does stays in `docs/agents/specs/api/endpoints/`.
- **Format:** follow the conventions in `docs/agents/specs.md`. The file starts with a title and a one-line `Purpose:` statement, open questions are marked **Pending decision**, and everything is in English.

**Decisions already made in #55:**
- **Hand-written.** Classes live in `Pidgey\Http\`. No router or middleware library, and no new runtime dependency (`architecture/dependencies.md`).
- **Routing.** Routes are registered dev-API style (method + path with `:param` → handler class). They are ordered, the first match wins, and there is a guaranteed 404 fallback. The router captures the `:param` values and passes them to the handler.
- **Handlers.** Tent-style base class (a `final` entry method calls an abstract processing method). A new instance is built per request through a factory that injects its dependencies.
- **Middlewares.** Tent-style pre/post hooks (`processRequest` / `processResponse`).
  - A request hook can answer early by setting a response on the processing request.
  - Middlewares are attached globally and per route.
  - Their order follows the guard order in `api/conventions/format.md`.
- **Error handling.** Exceptions are turned into the JSON error envelope around the whole dispatch, not by a middleware.

### Reference

`/Users/darthjee/messages/php-request-handler/index.md` (overview, Tent vs dev API, porting checklist).

### Sequencing

Comes first: it creates the area that the other sub-issues fill in.

## Problem

There is no spec area for request handling yet:

- The front-controller description lives in a single "Entry point" paragraph in `architecture/backend.md`.
- Several docs call the HTTP-layer classes "controllers", while the Epic settled on "request handlers" and "middlewares".
- The six sibling sub-issues (#57–#62) need existing files and a stable index to land in. Otherwise their PRs would add broken links or conflict on the same index table.

## Expected Behavior

### Deliverables

- `docs/agents/specs/request-handler.md`, with exactly three parts (see [Index content](#index-content)):
  - a `Purpose:` line
  - an annotated flow diagram
  - a table linking the six detail files (`rewrite.md`, `request-response.md`, `routing.md`, `handlers.md`, `middlewares.md`, `front-controller.md`), each with a one-line description; the files are filled in by the sibling sub-issues
- The six detail files as stubs (see [Placeholder detail files](#placeholder-detail-files))
- `docs/agents/specs.md`: add a `request-handler` row to the Index table.
- `docs/agents/specs/architecture/backend.md`:
  - Shorten the "Entry point" section to a one-paragraph summary that links to `request-handler.md` as the source of truth. Keep the static-file and `*.json` rules consistent with `frontend/build.md`.
  - In the Layers table, the HTTP row: replace "controllers" with "request handlers, middlewares" and link `request-handler.md`.
- Other docs (see [Other specs to touch](#other-specs-to-touch)).

### Scope

#### In scope

- `docs/agents/specs/request-handler.md`: the `Purpose:` line, the annotated diagram and the parts table.
- The six stub detail files: title, final `Purpose:`, the Pending pointer and the "not the source of truth" note.
- The `docs/agents/specs.md` row, after `architecture`.
- `specs/architecture/backend.md`: the shortened "Entry point" (keeping the routing rules) and the Layers terminology fix.
- Links and terminology in `docs/agents/architecture.md`, `specs/overview/components.md`, `specs/architecture/repository-layout.md` and `docs/agents/folder-structure.md`.

#### Out of scope

- The content of the six detail files (#57–#62).
- Changing any decision taken in #55.
- Rewriting the "stub"/"placeholder" wording that describes the current state. That belongs to the implementation issues.
- Any code, `.htaccess` or tests.
- The guard order in the `api/` specs. It is linked, not changed.
- `.claude/agents/backend.md` and `docs/agents/contributing.md`: they only say "front controller", which is still correct. The stale `static/` path in `.claude/agents/backend.md:14` is tracked in a separate issue.
- The sibling/Epic sync rules (edge cases 2, 3 and 5) are added as notes on #55 and #57–#62 directly on GitHub, not through this issue's PR.

## Solution

### Index content

`request-handler.md` contains a `Purpose:` line, an annotated flow diagram and the parts table. Nothing else.

- **The diagram** maps each step of the flow to the detail file that owns it:

  ```
  HTTP request
    → Apache + .htaccess                 (rewrite.md)
    → source/public/index.php            (front-controller.md)
    → Request / processing request       (request-response.md)
    → route match, :params, 404          (routing.md)
    → global + per-route request hooks   (middlewares.md)
    → handler                            (handlers.md)
    → response hooks                     (middlewares.md)
    → Response → ResponseSender          (request-response.md)
  ```

- **No decisions summary in the index.** Each decision (hand-written, routing style, pre/post hooks, per-request handlers, error handling around dispatch) is recorded only in the detail file that owns it, so it is never duplicated and can't drift.
- **Precedent:** `frontend.md` and `architecture.md` are `Purpose:` + table. `api/conventions.md` adds a short block between them. The diagram follows that precedent.
- **Rejected:**
  - Purpose + table only, with the diagram moved to `front-controller.md`. The diagram is the map of the whole area, so it belongs in the index.
  - A full index with a decisions summary. It duplicates the detail files.

### Placeholder detail files

#56 also creates the six detail files as **stubs**, so every link in the index works from the start and each sibling PR touches only its own file (#59, #60 and #61 run in parallel and would otherwise conflict on the index table).

Each stub has a title, a final `Purpose:` line and a pointer to the issue that fills it in:

```markdown
# Routing

Purpose: define how a request is mapped to a handler class: route registration, `:param` matching and capture, and the 404 fallback.

**Pending decision:** content to be specified in #59. Until then this file is not the source of truth.
```

| File | Owner | `Purpose:` |
|---|---|---|
| `request-handler/rewrite.md` | #57 | define how Apache rewrites requests to the front controller: the `.htaccess` rules, which paths bypass PHP, and the Apache requirements. |
| `request-handler/request-response.md` | #58 | define the request and response models, the processing request (route params, attached data, early response), and how a response is sent. |
| `request-handler/routing.md` | #59 | define how a request is mapped to a handler class: route registration, `:param` matching and capture, and the 404 fallback. |
| `request-handler/handlers.md` | #60 | define the request handler base class: its template method, its factory and lifecycle, and where body validation runs. |
| `request-handler/middlewares.md` | #61 | define the middleware contract (request and response hooks, early answer), how middlewares are attached globally and per route, and their order. |
| `request-handler/front-controller.md` | #62 | define what `source/public/index.php` does: bootstrap, dispatch, and error handling around the whole dispatch. |

The index table's one-line descriptions are derived from these `Purpose:` lines.

- Settling the six `Purpose:` lines together here sets each sibling's boundary.
- A sibling may refine its own `Purpose:` line, keeping the index table description in sync.
- **Rejected:**
  - Plain names without links, each sibling adding its link later. Every sibling PR would edit the same table.
  - Linking files that don't exist yet. The links would stay broken until #57–#62 land.

### Other specs to touch

A search for "front controller", `index.php` and "routing" across `docs/agents/` found these. The new term is **request handlers** (not "controllers") and **middlewares**.

**Updated by #56:**

| File | Change |
|---|---|
| `docs/agents/specs.md` | Add the `request-handler` row to the Index table, **right after `architecture`** (it details the backend's HTTP layer). Row: `\| [request-handler](specs/request-handler.md) \| How HTTP requests flow through the backend: Apache rewrite, front controller, request/response, routing, request handlers, middlewares. \|` |
| `specs/architecture/backend.md` | Shorten "Entry point" + link. In the Layers HTTP row, "controllers" → "request handlers, middlewares" + link. |
| `docs/agents/architecture.md:35` | `Pidgey\Http\` row: "controllers … auth middleware" → "request handlers, middlewares (cookie-session gate, Bearer-token auth)" + link to `specs/request-handler.md`. |
| `specs/overview/components.md:18` | Backend row: add a link to `request-handler.md` next to `backend.md`. |
| `specs/architecture/repository-layout.md:27` | Keep the "Initially a stub `index.php` …" wording (still true), and add that the front controller is specified in `request-handler/front-controller.md`. |
| `docs/agents/folder-structure.md:33` | Keep "currently a placeholder stub", and point "replaced by the front controller later" to `specs/request-handler.md`. |

**Left unchanged (still accurate):**
- `specs/architecture.md:8`: its description of `backend.md` stays true.
- `frontend/build.md:20` and `frontend/development.md:76` ("falls through to `index.php` (JSON 404)"): these match the 404 fallback.

Current-state wording ("stub", "placeholder") is not rewritten here. That happens in the implementation issues, when the stub is actually replaced. #56 only adds the links.

### Edge cases

1. **`backend.md` keeps its routing rules.**
   - The shortened "Entry point" summary still states, in one or two sentences:
     - existing files and directories bypass PHP
     - `*.json` paths are always routed to the API, even under `/admin/`
     - API paths are root-relative with no prefix; only `POST /v1/messages` has `/v1`
   - `frontend/build.md` relies on the first two.
   - Only the mechanics (bootstrap, dispatch) move to `request-handler.md`.
   - #57 and #59 link back to these rules rather than redefining them.
   - No inbound link uses an anchor into `backend.md`, so shortening it breaks nothing.
2. **Siblings keep the index in sync.** A sibling PR that changes a step of the flow (e.g. #59 choosing 405, or #61 deciding response hooks are skipped after an early answer) updates the diagram and its own table row in the same PR.
3. **No leftover stubs.** Before Epic #55 is closed, `grep -rn 'content to be specified in #' docs/agents/specs/request-handler/` must return nothing. A sibling merged into another or dropped must have its stub filled or removed (and its index row updated).
4. **Stubs are not the source of truth.** The stub text states that the file is not yet the specification. No implementation issue starts on a part whose stub is still unfilled. Stub wording:

   ```markdown
   **Pending decision:** content to be specified in #59. Until then this file is not the source of truth.
   ```

5. **Renames and splits.** A sibling that renames its file or splits it into a folder (per the granularity rule in `specs.md`) updates the index table and every inbound link in the same PR.

## Benefits

- **One entry point.** The new spec area follows the repo's granularity rule (hub → area index → detail files), so readers have a single place to start.
- **No broken links, no conflicts.** The stubs keep every index link valid, and each sibling PR touches only its own file.
- **One term everywhere.** "Request handlers" and "middlewares" replace "controllers" across the docs.
- **No lost rules.** The rules that `frontend/build.md` relies on stay in `backend.md`.
