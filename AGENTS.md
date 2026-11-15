# Pidgey

Pidgey is an email-delivery service written in plain PHP. It sends transactional
email through generic SMTP connections configured per organization, exposes a JSON
API (authenticated by scoped API tokens) for sending messages, and ships a React
administrative UI to manage organizations, users, SMTP connections, and API tokens.

## Documentation entry point (mandatory)

All project documentation lives under `docs/agents/`; see [Documentation](#documentation).
The area docs are indexed in [`docs/agents/specs.md`](docs/agents/specs.md).

## Rules for agents

1. **Read the relevant docs before writing code.** Find the area in
   [Documentation](#documentation) or the `specs.md` hub, then read its hub and the
   detail files that apply.
2. **Docs describe the system as built.** When a change alters behaviour or a decision,
   update the affected docs in `docs/agents/` in the same change set as the code.
3. **Respect pending decisions.** Items marked "Pending decision" must not be
   implemented by guessing; raise them or record the decision in the doc first.

## Documentation

All project documentation lives under [`docs/agents/`](docs/agents/):

| File | Contents |
|------|----------|
| [Docs hub](docs/agents/specs.md) | Index of the area docs, which describe the system as built. |
| [Overview](docs/agents/overview.md) | What Pidgey is and is not, goals, component map, terminology. |
| [Persistence](docs/agents/persistence.md) | Runtime database connection, transactions, initial schema migrations, entities, repositories and their tests. |
| [Request Handler](docs/agents/request-handler.md) | How HTTP requests flow through the backend: Apache rewrite, front controller, request/response, routing, request handlers, middlewares. |
| [Docker](docs/agents/docker.md) | Image layers, docker-compose services, volumes, Makefile targets. |
| [Organizations](docs/agents/organizations.md) | Organizations, users, bootstrap flow, organization scope, data model. |
| [Email](docs/agents/email.md) | SMTP connections, synchronous send flow, message scope, failure handling. |
| [API](docs/agents/api.md) | JSON API v1 surface, token authentication and scopes, conventions. |
| [Frontend](docs/agents/frontend.md) | React admin UI stack, layout, client layer, screens, build. |
| [Folder Structure](docs/agents/folder-structure.md) | Top-level directory layout and the role of each folder. |
| [Architecture](docs/agents/architecture.md) | Source layout, modules, code style, and implementation guidelines. |
| [Flow](docs/agents/flow.md) | Main runtime flow of the application. |
| [Deployment](docs/agents/deployment.md) | How the production deployment works as implemented: pipeline, variables, server, operations, known gaps. |
| [Contributing](docs/agents/contributing.md) | Commit guidelines, PR standards, code organization, and refactoring rules. |
| [Plans](docs/agents/plans/) | Implementation plans for ongoing or upcoming features. |
| [Issues](docs/agents/issues/) | Detailed issue files. |

### Issues (`docs/agents/issues/`)

Each file documents an issue in detail. Naming convention:

```
docs/agents/issues/<issue_id>_<issue_name>.md
```

Example: `docs/agents/issues/5_release_docker_image.md` for issue #5.

### Plans (`docs/agents/plans/`)

Each plan is a directory named after the issue ID and topic, containing one or more related files:

```
docs/agents/plans/<issue_id>_<topic>/<related_files>.md
```

Example: `docs/agents/plans/12_add-auth/plan.md` for issue #12.

## Commit Attribution

Commit co-author trailers follow arcanum's resolved `git.omit_model_coauthor` and `git.remove_coauthors` config (check them with `/arcanum-check-config`); this overrides Claude Code's own attribution reminder for every commit, including ones written by hand.
