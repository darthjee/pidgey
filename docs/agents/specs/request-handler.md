# Request Handler

Purpose: index of the specs describing how an HTTP request flows through the backend, from the Apache rewrite to the response.

```
HTTP request
  → Apache + .htaccess                 (rewrite.md)
  → source/public/index.php            (front-controller.md)
  → Request / processing request       (request-response.md)
  → CORS preflight answered            (front-controller.md; before routing, not a middleware)
  → route match, :params, 404          (routing.md)
  → request hooks: global, then route  (middlewares.md)
  → handler                            (handlers.md)
  → response hooks                     (middlewares.md; all run, also after an early response; skipped for errors)
  → errors → ErrorFormatter            (request-response.md; front-controller.md)
  → CORS headers, request logging      (front-controller.md; every final response, errors included)
  → Response → ResponseSender          (request-response.md)
```

| File | Content |
| --- | --- |
| [rewrite.md](request-handler/rewrite.md) | Apache rewrite to the front controller: `.htaccess` rules, paths that bypass PHP, Apache requirements. |
| [front-controller.md](request-handler/front-controller.md) | What `source/public/index.php` does: bootstrap, dispatch, error handling around the dispatch. |
| [request-response.md](request-handler/request-response.md) | Index of the `request-response/` folder: request and response models, the processing request (route params, attached data, early response), error responses, and how a response is sent. |
| [routing.md](request-handler/routing.md) | Mapping a request to a handler class: the `Router` and route registration, the route files (`source/config/routes.php` and one file per area), `:param` matching and capture, the 404 fallback. |
| [middlewares.md](request-handler/middlewares.md) | Middleware contract (`Middleware`, `BaseMiddleware`), global and per-route attachment, order, the concrete v1 middlewares by name, the `Caller` interface, and why CORS and request logging are front-controller steps. |
| [handlers.md](request-handler/handlers.md) | Request handler base class (`RequestHandler`): template method, `HandlerFactory` and `Container` shape, lifecycle, `intParam()`, body validation, errors. |
