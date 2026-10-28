# Request Handler

Purpose: index of the specs describing how an HTTP request flows through the backend, from the Apache rewrite to the response.

```
HTTP request
  → Apache + .htaccess                 (rewrite.md)
  → source/public/index.php            (front-controller.md)
  → Request / processing request       (request-response.md)
  → route match, :params, 404          (routing.md)
  → global + per-route request hooks   (middlewares.md)
  → handler                            (handlers.md)
  → response hooks                     (middlewares.md; all run, also after an early response)
  → Response → ResponseSender          (request-response.md)
```

| File | Content |
| --- | --- |
| [rewrite.md](request-handler/rewrite.md) | Apache rewrite to the front controller: `.htaccess` rules, paths that bypass PHP, Apache requirements. |
| [front-controller.md](request-handler/front-controller.md) | What `source/public/index.php` does: bootstrap, dispatch, error handling around the dispatch. |
| [request-response.md](request-handler/request-response.md) | Index of the `request-response/` folder: request and response models, the processing request (route params, attached data, early response), error responses, and how a response is sent. |
| [routing.md](request-handler/routing.md) | Mapping a request to a handler class: the `Router` and route registration, the route files (`source/config/routes.php` and one file per area), `:param` matching and capture, the 404 fallback. |
| [middlewares.md](request-handler/middlewares.md) | Middleware contract, global and per-route attachment, and order. |
| [handlers.md](request-handler/handlers.md) | Request handler base class: template method, factory and lifecycle, body validation. |
