# Backend Plan: StaticFileHandler and frontend routes (`/`, `/assets/*`, hash redirect)

Main plan: [plan.md](plan.md)

## Overview
Add two handlers and one route file:
- `StaticFileHandler` serves `index.html` at `/` and the hashed bundles under `/assets/*` from `source/static/`;
- `HashRedirectHandler` is the router's GET fallback and answers `302 Location: /#<raw REQUEST_URI>`;
- `source/config/routes/frontend.php` registers the two routes.

Also add a raw-URI accessor to `Request`/`ProcessingRequest` and update `RoutesTest`.

## Context
- Specs: `docs/agents/specs/request-handler/static-files.md` (normative) and `routing.md` (prefix routes, GET fallback, route files).
- **Depends on #122.** It adds `/*` prefix routes in `Route` and `Router::fallback(Handler::class)`.
  - If #122 has not merged when this starts, merge `origin/main` once it has. Do not reimplement either feature here.
  - Read the merged `Router`/`RouteMatch` to see what `pattern` a fallback match carries, and assert on that in `RoutesTest`.
- `FrontController` builds handlers with `new ($match->handlerClass)()`, with no arguments. Every constructor argument must therefore be optional, with production defaults.
- `ErrorFormatter` already uses the `?callable $logger` pattern, defaulting to `error_log()`. Reuse it.
- `Response::addHeader()` rejects CR/LF, and `NotFoundException` turns into the JSON 404 through `ErrorFormatter`.

## Steps
- [01 — Raw URI accessor](backend/01-raw-uri.md)
- [02 — StaticFileHandler](backend/02-static-file-handler.md)
- [03 — HashRedirectHandler and frontend routes](backend/03-redirect-and-routes.md)

## CI Checks
- `source`: `make tests` (`composer lint && composer tests` in the tests container). CI job: the PHP tests/lint job in `.circleci/config.yml`.

## Notes
- Keep 100% line coverage. `source/static/` does not exist in the repo (it is git-ignored, and moving the build there is a separate sub-issue), so tests must never rely on it.
- Before the PR is marked ready, ask the `security` agent to review the path validation and the redirect.
- Out of scope: the build/deploy move to `source/static/`, conditional GET (ETag/304) and a full CSP.
