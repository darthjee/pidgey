# Pidgey Docs

This file indexes Pidgey's documentation. The docs describe the system as built and are
updated in the same change set as the code. This hub also indexes the specs of features not
built yet, in the [Specs](#specs) table; a spec is removed once the as-built docs cover it.

## Index

| Doc | Purpose |
| --- | --- |
| [overview](overview.md) | What Pidgey is and is not, goals, component map, terminology. |
| [architecture](architecture.md) | Repository layout, backend structure, database access, dependency policy. |
| [persistence](persistence.md) | Runtime database connection, transactions, initial schema migrations, entities, repositories and their tests. |
| [request-handler](request-handler.md) | How HTTP requests flow through the backend: Apache rewrite, front controller, request/response, routing, request handlers, middlewares. |
| [docker](docker.md) | Image layers, docker-compose services, volumes, Makefile targets. |
| [organizations](organizations.md) | Organizations, users, bootstrap flow, organization scope, data model. |
| [email](email.md) | SMTP connections, synchronous send flow, message scope, failure handling. |
| [api](api.md) | JSON API v1 surface, token authentication and scopes, conventions. |
| [frontend](frontend.md) | React admin UI stack, layout, client layer, screens, build. |
| [clients](clients.md) | Client libraries under `clients/`: the `pidgey-client` Node.js package scaffold, its checks and CI job. |
| [contributing](contributing.md) | Commit, PR and code-organization rules, plus testing, linting and definition of done in [contributing/testing.md](contributing/testing.md). |
| [deployment](deployment.md) | The production deployment as implemented: pipeline, variables, server, operations, known gaps. |

## Specs

Features not built yet. Each spec is temporary: it is removed, with its row, once the as-built
docs of its area cover it.

| Spec | Purpose |
| --- | --- |
| [node-client](specs/node-client.md) | Node.js library `pidgey-client`, CLI `pidgey-cli` and the `/v1` OpenAPI document (epic #219). The scaffold is built and described in [clients.md](clients.md); the rest is not built yet. |

## Conventions

- Undecided items are marked explicitly as **Pending decision**.
- English only.
- When a new area is added, index it in the table above.
- Each area is a hub `docs/agents/<area>.md` indexing its detail files under `docs/agents/<area>/`.
