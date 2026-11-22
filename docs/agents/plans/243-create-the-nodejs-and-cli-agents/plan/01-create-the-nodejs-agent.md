# Create the nodejs agent

Write `.claude/agents/nodejs.md`, following the layout of `.claude/agents/frontend.md` (frontmatter, Your scope, Stack, Commands, Code conventions, Development cycle, Conventions).

- **Frontmatter:** `name: nodejs`; `description: Pidgey Node.js client library specialist. Use for any task involving the pidgey-client npm package: PidgeyClient, PidgeyError, send, error mapping, the retry policy, the OpenAPI-generated types, its Jasmine specs, or anything under clients/nodejs/.`; `tools: Read, Edit, Write, Bash` (no Agent tool).
- **Your scope:** everything inside `clients/nodejs/`: source, Jasmine specs, the committed `types/` file generated from the OpenAPI document, `package.json`, `yarn.lock`, `eslint.config.mjs`, the package README, its spec fixtures (including the `.d.ts` consumer fixture).
- **Do NOT touch:** `clients/cli/` (`cli`); `clients/scripts/`, CI, Docker, compose and the `Makefile` (`infra`); `source/` (`backend`), including the OpenAPI document it generates types from; `docs/agents/clients.md` + `docs/agents/clients/` (`product-owner`) and the rest of `docs/agents/` (`architect`); files directly under `clients/` (`architect`); any file outside `clients/nodejs/`.
- **Stack:** plain JavaScript, ESM only (`"type": "module"`, an `exports` map), no build step except the pack-time `.d.ts` generation; **zero runtime dependencies, ever**; only `fetch` and `AbortSignal` beyond the language (a later browser target stays possible); `engines` `>=22.12.0`; Yarn, Jasmine, `c8` at 100 % (lines, branches, functions, statements), ESLint 9 flat config, `tsc --allowJs --checkJs --noEmit`.
- **Commands:** `yarn lint`, `yarn typecheck`, `yarn test`, `yarn coverage`, always run in the compose Node service, never on the host. The exact `docker compose run …` lines and `make clients-checks` are marked *(planned)*, to be filled in by #246.
- **Code conventions:** JSDoc on every public export; specs named `*_spec.js`, mirroring the source tree; no `fdescribe` / `fit`; every `/* c8 ignore next */` carries a reason; specs use real `fetch` against an in-process `node:http` server on `127.0.0.1`, port `0`, never a stubbed `fetch`, and call `server.closeAllConnections()` before `server.close()`.
- **Development cycle:** lint → typecheck → coverage, looped until all three are clean.
- **Conventions:** read the specs while they exist, then `docs/agents/clients/nodejs.md`; `product-owner` updates the as-built docs in the same change set; never implement a **Pending decision** by guessing; consult `security` before adding any (dev)dependency.
- **Boundary rules** (shared with `cli.md`): a change spanning both packages is one PR, with `nodejs` dispatched first by `architect`; the public exports (`PidgeyClient`, `PidgeyError`, `VERSION`) are the only API the CLI uses, so changing them is a cross-package change; neither agent dispatches the other; renaming or removing an npm script that `clients/scripts/` calls needs `infra` in the same PR; a `/v1` OpenAPI change in `source/` is followed, in the same PR, by regenerating `types/`; library first at release.

## Files to Change
- `.claude/agents/nodejs.md` — new agent file.
