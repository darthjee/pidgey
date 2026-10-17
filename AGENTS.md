# Pidgey

Pidgey is an email-delivery service written in plain PHP. It sends transactional
email through generic SMTP connections configured per organization, exposes a JSON
API (authenticated by scoped API tokens) for sending messages, and ships a React
administrative UI to manage organizations, users, SMTP connections, and API tokens.

## Documentation entry point (mandatory)

All project documentation lives under `docs/agents/`. The entry point is
[`docs/agents/specs.md`](docs/agents/specs.md). Start there.

## Rules for agents

1. **Read the specs before writing any code.** Identify the specs relevant to the
   task from the hub (`docs/agents/specs.md`), then read the spec index and the
   detail files that apply.
2. **Specs are the source of truth.** Code must not deviate from them. If a decision
   changes, update the spec first (in the same change set), then the code.
3. **Respect pending decisions.** Items marked "Pending decision" must not be
   implemented by guessing; raise them or record the decision in the spec first.
4. **Follow the spec granularity rule** below when adding or growing specs.

## Spec granularity rule

Specs are organized in three levels:

- `docs/agents/specs.md` — the hub, indexing every spec.
- `docs/agents/specs/<name>.md` — a short index for one spec area, linking to its
  detail files.
- `docs/agents/specs/<name>/*.md` — the detail files holding the actual content.

A spec may start as a single file `docs/agents/specs/<name>.md`. When it grows too
large to stay coherent in one file, it must be split into a folder
`docs/agents/specs/<name>/` containing `*.md` files, and `<name>.md` becomes a short
index of the folder contents. The same rule applies recursively to any detail file
that outgrows itself (`<name>/<topic>.md` → `<name>/<topic>/*.md`).

These specs describe where the project is going. As the implementation matures they
will be replaced by documentation describing the actual project.
