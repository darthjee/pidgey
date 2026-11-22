# Issue: Create the nodejs and cli agents

## Description

- Parent epic: #219 (wave 2, created by #228). Specs: `docs/agents/specs/node-client.md` and `docs/agents/specs/node-client/*.md` are the source of truth (hub section Lifecycle).
- Owner: `architect`.
- Depends on: nothing. Blocks #246.

## Problem

Epic #219 adds a `clients/` folder with two Node.js packages: the library `pidgey-client` (`clients/nodejs/`) and the CLI `pidgey-cli` (`clients/cli/`). No agent under `.claude/agents/` owns them today, so client work has no scope boundary and would be routed to `backend` or `frontend`.

## Expected Behavior

- `.claude/agents/nodejs.md` and `.claude/agents/cli.md` exist, each scoped to its folder.
- `architect.md` lists both agents, routes `clients/` work to them, and lists `clients` among the `product-owner` area docs.
- `product-owner.md` lists `clients` among its area docs; `infra.md` owns `clients/scripts/` and must not touch `clients/nodejs/` or `clients/cli/`.
- `folder-structure.md` and `architecture/repository-layout.md` list `clients/` and its sub-folders with their owners, marked *(planned)*.
- Routing check: matched against the descriptions, a sample of issue titles lands on the right agent (for example "retry policy" → `nodejs`, "stdin cap" → `cli`, "`clients_checks` job" → `infra`, "admin login screen" → `frontend`).
- Nothing listed under Scope / Out is changed.

### Testing

Docs and agent files only, so no test suite runs: every link added resolves, each agent file has valid frontmatter (`name`, `description`, `tools`), and the routing check of Expected passes. Only the `frontend.md` description narrows: no existing rule of another agent changes.

## Solution

Follow `docs/agents/specs/node-client/packaging.md`, section Ownership:

1. Create `.claude/agents/nodejs.md` (owns `clients/nodejs/`: source, Jasmine specs, `package.json`, `yarn.lock`, `eslint.config.mjs`, the package README) and `.claude/agents/cli.md` (owns `clients/cli/`: `bin/`, source, Jasmine specs, `package.json`, `yarn.lock`, `eslint.config.mjs`), in the format of the existing agents.
2. `.claude/agents/architect.md`:
   - add both agents to its agents table and name `clients/` in the ownership split, so client work is never routed to `backend` or `frontend`;
   - add `clients` to the area docs it lists as owned by `product-owner`, so `docs/agents/clients.md` and `docs/agents/clients/` are never treated as its own;
   - add the files directly under `clients/` to its own scope (see Edge cases).
3. `.claude/agents/backend.md`: one line: a PR changing the `/v1` OpenAPI document also has `nodejs` regenerate the committed `types/` file, coordinated by `architect` (see Edge cases).
4. `.claude/agents/frontend.md`: narrow its `description` to the admin UI (see Descriptions and routing); the body is unchanged.
5. `.claude/agents/product-owner.md`: add `clients` to its list of area docs (`docs/agents/clients.md` + `docs/agents/clients/`).
6. `.claude/agents/infra.md`: add `clients/scripts/` to "You own" and to its `description`; add `clients/nodejs/` (`nodejs`) and `clients/cli/` (`cli`) to its "Do NOT touch".
7. `docs/agents/folder-structure.md` and `docs/agents/architecture/repository-layout.md`: add a `clients/` row listing `nodejs/`, `cli/` and `scripts/` with their owners, marked *(planned)* (the convention `folder-structure.md` already uses) until the folder exists.

### Agent content

Both files follow the layout of `.claude/agents/frontend.md`, with content taken from the specs.

**Both agents:**

- **Frontmatter:** `name` (`nodejs` / `cli`), the `description` below, and `tools: Read, Edit, Write, Bash`, like `frontend` (no Agent tool: see the boundary rules).
- **Scope / Do NOT touch:** the other package (`nodejs` ↔ `cli`); `clients/scripts/`, CI, Docker and the `Makefile` (`infra`); `source/` (`backend`), including the OpenAPI document, which `nodejs` generates types from but never edits; `docs/agents/clients.md` + `docs/agents/clients/` (`product-owner`) and the rest of `docs/agents/` (`architect`).
- **Stack** (`packaging.md`, sections Stack and Node.js version): plain JavaScript, ESM only, no build step; `engines` `>=22.12.0`; Yarn, Jasmine, `c8` at 100 %, ESLint 9 flat config, `tsc --allowJs --checkJs --noEmit`.
- **Conventions:** JSDoc on every public export; specs named `*_spec.js`, mirroring the source tree; no `fdescribe` / `fit`; a `/* c8 ignore next */` always carries a reason; never install packages or run `yarn` on the host; consult `security` before adding any dependency.
- **Docs rule:** read `docs/agents/specs/node-client.md` and `docs/agents/specs/node-client/*.md` while they exist, then the as-built `docs/agents/clients/`; `product-owner` updates those in the same change set as the code; never implement a **Pending decision** by guessing.
- **Development cycle:** lint → typecheck → coverage, looped until all three are clean.
- **Commands:** the `yarn` script names fixed by the spec (`lint`, `typecheck`, `test`, `coverage`) and the rule that they run in the compose Node service; the exact `docker compose run …` lines and `make clients-checks` are marked *(planned)*. #246 fills them in for `nodejs.md` and #249 for `cli.md` (both bodies carry that hand-off).

**`nodejs` only:** zero runtime dependencies, ever; only `fetch` and `AbortSignal` (a later browser target stays possible); `.d.ts` generated at pack time; specs use real `fetch` against an in-process `node:http` server on `127.0.0.1`, port `0`, never a stubbed `fetch`, and call `closeAllConnections()` on teardown.

**`cli` only:** the thin-layer rule (no HTTP code and no retry logic; if a change needs to know what a status or code means, it belongs in the library and goes to `nodejs`); `parseArgs` in strict mode; the token never comes from argv; the in-process specs (`Arguments_spec.js`, `Body_spec.js`, `ExitCode_spec.js`) and the subprocess spec (`bin/Pidgey_spec.js`).

#### Boundary between `nodejs` and `cli`

Both agent files state these rules (and `architect.md` the coordination part):

1. **A change spanning both packages is one PR** (for example, a new library option the CLI exposes): `architect` dispatches `nodejs` first, then `cli`, since the CLI consumes the library's public API; `product-owner` updates both as-built docs in the same PR.
2. **The CLI imports only the public exports** of `pidgey-client` (`PidgeyClient`, `PidgeyError`, `VERSION`): never a relative `../nodejs/…` import, never a deep import.
3. **Neither agent dispatches the other** (they have no Agent tool): when `cli` needs a library change, it stops and reports to `architect`, and never works around the gap in the CLI (no local classification, HTTP or retry code).
4. **`clients/scripts/` is a contract with `infra`:** `infra` owns the scripts (`checks.sh`, `integration.sh`, the staging-copy pack, the smoke test); `nodejs` and `cli` own the npm scripts they call and their own spec fixtures (for example the `.d.ts` consumer fixture). Renaming or removing an npm script a shared script calls needs `infra` in the same PR.
5. **Library first at release:** a CLI feature relying on a new library API ships after the library release that provides it; the floor derived at pack time (#222) makes the published CLI require the library version it was tested with.

#### Descriptions and routing

The arcanum skills route work by matching each agent's `description`, so the wording decides where client work goes. Today `frontend` claims "JavaScript/JSX, …, Jasmine, ESLint, Yarn", which also matches client issues.

- `nodejs`: "Pidgey Node.js client library specialist. Use for any task involving the `pidgey-client` npm package: `PidgeyClient`, `PidgeyError`, `send`, error mapping, the retry policy, the OpenAPI-generated types, its Jasmine specs, or anything under `clients/nodejs/`."
- `cli`: "Pidgey command-line client specialist. Use for any task involving the `pidgey-cli` npm package and its `pidgey` command: flags, token file, environment fallbacks, stdin, exit codes, output, or anything under `clients/cli/`."
- `frontend` (description only): anchored to the admin UI ("… the React admin UI under `frontend/`: JavaScript/JSX, Vite, …") and adds "Not for the Node clients under `clients/`."
- `infra`: its description adds "the client check, integration and publish scripts under `clients/scripts/`".
- `architect.md` agents table: `nodejs` → `clients/nodejs/` (library `pidgey-client`); `cli` → `clients/cli/` (CLI `pidgey-cli`, thin layer over the library).

#### Alternatives considered

The two-agent split comes from `docs/agents/specs/node-client/packaging.md`, section Ownership, and is kept:

- **One `clients` agent** owning all of `clients/`: rejected. Nothing would enforce the thin-layer rule; two agents make it an ownership boundary, so a CLI change needing library code goes through `architect`.
- **Extend `frontend`** to `clients/`: rejected. `frontend` is the React admin UI (JSX, Vite, react-bootstrap); its conventions do not fit a published zero-dependency npm library, and routing would blur again.
- **One `nodejs` agent** owning both packages: rejected, for the same reason as one `clients` agent; it also fits badly with later runtimes under `clients/`, where the agent name follows the folder.
- **Naming the library agent `client` or `pidgey-client`:** rejected. Agents are named after their folder (`clients/nodejs/` → `nodejs`, `clients/cli/` → `cli`), so ownership is unambiguous from the path.

#### Edge cases

1. **Files directly under `clients/`** (a `clients/README.md`, a shared `.nvmrc` or ESLint config): owned by `architect`, like the repository root. The packages share no config: each keeps its own `eslint.config.mjs`, `package.json` and `yarn.lock`.
2. **A `/v1` change in `source/`** makes the contract spec fail until `nodejs` regenerates the committed `types/` file, which `backend` cannot touch: a PR that changes the OpenAPI document also regenerates `types/` (`nodejs`), coordinated by `architect`. `backend.md` gains one line stating this.
3. **Client work before the package exists:** only the scaffold issues create a package folder (#246 for `clients/nodejs/`, #249 for `clients/cli/`); until then, the agents only take issues that depend on them.
4. **Root files the clients need** (`.gitignore` entries such as `node_modules/` and `coverage/`, `.codacy.yml` exclusions): they stay with their current owners (`architect` for root files, `infra` for compose and CI); #246 and #249 add them through those owners.

### Scope

**In:** the two new agent files, the `frontend.md` description, one line in `backend.md`, and the ownership updates above in `architect.md`, `product-owner.md`, `infra.md`, `folder-structure.md` and `architecture/repository-layout.md`.

**Out** (each lands with the code it describes):

- the `Clients` row of `AGENTS.md`, the `clients` row of `docs/agents/specs.md`, and `docs/agents/clients.md` + `docs/agents/clients/`: #246;
- the `clients/` → `clients_checks` row of the check-jobs table in `docs/agents/contributing.md`: #246, which creates the job;
- dropping the *(planned)* marker of the `clients/` rows: #246 (library and `clients/scripts/`) and #249 (CLI);
- the rest of `backend.md` and the body of `frontend.md`: unchanged, their "any file outside `source/`" / "any file outside `frontend/`" rule already keeps them out of `clients/`;
- any package code, CI, `Makefile` or compose change.

## Benefits

- Client work has an owner from the first commit of `clients/`, and is never routed to `backend` or `frontend`.
- The thin-layer rule becomes an ownership boundary: CLI code that needs library behaviour has to go through `architect`, not into the CLI.
- No doc describes a folder before it exists: the *(planned)* markers and the command hand-offs land with #246 and #249.
