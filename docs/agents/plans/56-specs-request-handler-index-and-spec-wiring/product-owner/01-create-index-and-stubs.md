# Create the request-handler index and stub detail files

## Index

Create `docs/agents/specs/request-handler.md` with exactly three parts. Add no decisions summary: each decision belongs only in the detail file that owns it.

1. Title `# Request Handler` and a `Purpose:` line, e.g. `Purpose: index of the specs describing how an HTTP request flows through the backend, from the Apache rewrite to the response.`
2. The annotated flow diagram, in a fenced block:

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

3. A `| File | Content |` table, in the same style as `docs/agents/specs/frontend.md`. It links each of the six detail files, in this order: rewrite, front-controller, request-response, routing, middlewares, handlers. Each description is a one-line summary derived from that file's `Purpose:` line below.

## Stub detail files

Create these six files under `docs/agents/specs/request-handler/`. Each stub contains only a title, the `Purpose:` line and the Pending pointer:

```markdown
# <Title>

Purpose: <purpose>

**Pending decision:** content to be specified in #<owner>. Until then this file is not the source of truth.
```

| File | Title | Owner | Purpose |
|---|---|---|---|
| `rewrite.md` | Apache Rewrite | #57 | define how Apache rewrites requests to the front controller: the `.htaccess` rules, which paths bypass PHP, and the Apache requirements. |
| `request-response.md` | Request and Response | #58 | define the request and response models, the processing request (route params, attached data, early response), and how a response is sent. |
| `routing.md` | Routing | #59 | define how a request is mapped to a handler class: route registration, `:param` matching and capture, and the 404 fallback. |
| `handlers.md` | Request Handlers | #60 | define the request handler base class: its template method, its factory and lifecycle, and where body validation runs. |
| `middlewares.md` | Middlewares | #61 | define the middleware contract (request and response hooks, early answer), how middlewares are attached globally and per route, and their order. |
| `front-controller.md` | Front Controller | #62 | define what `source/public/index.php` does: bootstrap, dispatch, and error handling around the whole dispatch. |

## Files to Change
- `docs/agents/specs/request-handler.md` — new area index.
- `docs/agents/specs/request-handler/rewrite.md` — new stub.
- `docs/agents/specs/request-handler/request-response.md` — new stub.
- `docs/agents/specs/request-handler/routing.md` — new stub.
- `docs/agents/specs/request-handler/handlers.md` — new stub.
- `docs/agents/specs/request-handler/middlewares.md` — new stub.
- `docs/agents/specs/request-handler/front-controller.md` — new stub.
