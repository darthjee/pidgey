# Pidgey Specs

This folder defines the intended design of Pidgey: what it is, how the repository is
organized, how it runs in Docker, how organizations, email sending and the API work,
how the React admin UI is built, how it is deployed, and how it is tested. These
specs are the **source of truth for implementation**: code must not deviate from
them without updating the relevant spec first. They describe the target state and
will later be replaced by documentation of the actual project.

## Index

| Spec | Purpose |
| --- | --- |
| [overview](specs/overview.md) | What Pidgey is and is not, goals, component map, terminology. |
| [architecture](specs/architecture.md) | Repository layout, backend structure, database access, dependency policy. |
| [docker](specs/docker.md) | Image layers, docker-compose services, volumes, Makefile targets. |
| [organizations](specs/organizations.md) | Organizations, users, bootstrap flow, organization scope, data model. |
| [email](specs/email.md) | SMTP connections, synchronous send flow, message scope, failure handling. |
| [api](specs/api.md) | JSON API v1 surface, token authentication and scopes, conventions. |
| [frontend](specs/frontend.md) | React admin UI stack, layout, client layer, screens, build. |
| [deployment](specs/deployment.md) | CircleCI jobs, image release, frontend deploy. |
| [testing](specs/testing.md) | Backend and frontend testing, linting, definition of done. |

## Spec granularity rule

Specs are organized in three levels:

- `docs/agents/specs.md` (this file) — the hub, indexing every spec.
- `docs/agents/specs/<name>.md` — a short index for one spec area, linking to its
  detail files.
- `docs/agents/specs/<name>/*.md` — the detail files holding the actual content.

A spec may start as a single file `docs/agents/specs/<name>.md`. When it grows too
large to stay coherent in one file, it must be split into a folder
`docs/agents/specs/<name>/` containing `*.md` files, and `<name>.md` becomes a short
index of the folder contents. The same rule applies recursively to detail files.

## Conventions

- Every spec file begins with a title and a one-line purpose statement.
- Undecided items are marked explicitly as **Pending decision**.
- English only.
- When a new spec area is added, it must be indexed in the table above.
