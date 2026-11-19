# Packaging and testing specs

Write `docs/agents/specs/node-client/packaging.md` from #219's "Packages", "Stack", "Agents and ownership" and "Compatibility and versioning":
- Packages `pidgey-client` (`clients/nodejs/`) and `pidgey-cli` (`clients/cli/`), no root workspace, `file:../nodejs` during development; npm name notes (`pidgey` taken by an unrelated package) and the `0.0.0` reservation (#227).
- Stack: plain JavaScript, ESM only, zero runtime dependencies, Yarn, Jasmine, nyc/c8, ESLint 9 flat config, JSDoc on public exports.
- Ownership: new `nodejs` and `cli` agents (created by a later sub-issue).
- Versioning: first release `0.1.0`, `0.x` semver rules, the breaking / not-breaking lists, `CHANGELOG.md` per package, independent from the backend version, server compatibility statement.
- `**Pending decision**` markers: #226 (Node.js version and `engines`), #222 (CLI → library range), #223 (publishing mechanism; publish rights = backend deploy rights already fixed), #225 (supply chain: 2FA, provenance).

Write `docs/agents/specs/node-client/testing.md` from #219's "Testing" and "CI":
- Approach: real `fetch` against an in-process `node:http` server; backoff via tiny `retryDelayMs` and a spy.
- Spec files per package (library, CLI, contract), the `clients_integration` end-to-end job (Pidgey + MySQL + Mailpit, library and CLI, `409`/`401` cases), `clients_checks`, the backend OpenAPI checks, `make tests` alignment.
- `**Pending decision** (#220)` for pack smoke test, `.d.ts` type check, CLI subprocess tests, coverage threshold, Node.js version matrix.

## Files to Change
- `docs/agents/specs/node-client/packaging.md` — new.
- `docs/agents/specs/node-client/testing.md` — new.
