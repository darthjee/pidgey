# Issue: Write the Node client specs

## Description

First sub-issue of epic #219 ("Create Clients"): write the specs of the Node.js client library `pidgey-client` and the command-line client `pidgey-cli` as a hub `docs/agents/specs/node-client.md` plus detail files under `docs/agents/specs/node-client/`, transcribing the decisions of #219. Docs only: no code, CI or agent change.

- Parent epic: #219. This is the first sub-issue; every enhancement and implementation sub-issue builds on these files.
- The spec files are temporary: a final sub-issue of #219 removes them once the as-built docs under `docs/agents/clients/` cover them.
- Owner: `product-owner`.

## Problem

Epic #219 ("Create Clients") decides a Node.js library (`pidgey-client`, folder `clients/nodejs/`) and a command-line client (`pidgey-cli`, folder `clients/cli/`, command `pidgey`) for Pidgey's Bearer-token API `POST /v1/messages`, plus an OpenAPI document served at `GET /v1/openapi.json`. All those decisions currently live only in the epic's issue body. The enhancement and implementation sub-issues need one shared, editable source in the repository before any code exists.

`docs/agents/specs.md` is the hub where specs of features not built yet are indexed; there are none today, and the `docs/agents/specs/` folder does not exist.

## Expected Behavior

- The hub and every detail file exist, written in English, consistent with epic #219.
- `docs/agents/specs.md` indexes `docs/agents/specs/node-client.md`.
- Items still open are marked **Pending decision** with a link to the sub-issue that decides them (#220, #222, #223, and the performance/security and Node.js version enhancement sub-issues).
- No code, CI or agent definition changes.

## Solution

Create the specs as a hub plus detail files, transcribing the decisions of epic #219 (Proposal, Design decisions, Alternatives considered, Edge cases, Implementation notes, Testing):

| File | Content |
| --- | --- |
| `docs/agents/specs/node-client.md` | Hub: purpose, scope and non-goals, index of the files below, open items with the sub-issue that decides each. |
| `docs/agents/specs/node-client/library.md` | `PidgeyClient` options (`token`, `baseUrl`, `maxRetries`, `retryDelayMs`, `retryAmbiguous`, `timeoutMs`), `send(message, { signal })`, the v1 field whitelist, headers, `redirect: 'error'`, abort, timeout, success passthrough. |
| `docs/agents/specs/node-client/errors-and-retry.md` | `PidgeyError` (`statusCode`, `code`, `message`, `details`, `smtp`, `possiblySent`), client-local codes (`CLIENT_CONFIGURATION`, `NETWORK_ERROR`, `INVALID_RESPONSE`, `ABORTED`), the retry table by `error.code`, ambiguous vs unambiguous failures, backoff. |
| `docs/agents/specs/node-client/cli.md` | Flags, `PIDGEY_TOKEN`/`PIDGEY_URL` fallbacks, stdin rules, exit codes `0`/`1`/`2`/`130`, output rules, `parseArgs` strict mode. |
| `docs/agents/specs/node-client/openapi.md` | The `/v1` OpenAPI 3.1 document, `GET /v1/openapi.json`, generated types and `.d.ts`, contract tests, the additive-only rule and the breaking-change diff in CI. |
| `docs/agents/specs/node-client/packaging.md` | Packages and names, ESM-only stack, zero runtime dependencies, versioning (`0.1.0`, `0.x` semver, breaking list), compatibility, publishing (open: #222, #223). |
| `docs/agents/specs/node-client/testing.md` | Test approach (real `fetch` against a local `node:http` server), spec files per package, `clients_checks` and `clients_integration` jobs (open: #220). |

The split above is a proposal. Each file covers one topic; if a file grows to mix topics, split it further under `docs/agents/specs/node-client/` and index the new file from the hub.

### Conventions

- **Indexing.** `docs/agents/specs.md` gets a separate `## Specs` table (features not built yet), with one row for `docs/agents/specs/node-client.md`; its intro sentence "there are none today" is updated. The area index table is not touched (there is no `clients` area until the code lands).
- **Pending markers.** Every open item is written as `**Pending decision** (#<issue>): <one-line question>`, in the file where the decision will land, so each enhancement sub-issue (#220, #222, #223, #225, #226) can find and replace its own markers. The hub lists all of them.
- **Source of truth.** Once this sub-issue merges, the spec files are the source of truth; the epic #219 body is not kept in sync and later decisions are recorded only in the specs.
- **Conflicts.** If transcribing reveals a conflict between #219 and the existing as-built docs (`docs/agents/api/`, `docs/agents/email/`), the as-built docs win for current behaviour, and the conflict is raised on the PR instead of being silently resolved.
- **Examples.** Examples use placeholder tokens (`pdg_example…`) and `example.com` hosts only; never a real token or host.

## Benefits

- The enhancement sub-issues (#220, #222, #223, #225, #226) edit one shared, versioned source instead of the epic body.
- The implementation sub-issues are created from reviewed specs, so they start from settled decisions.
- Open items are explicit `Pending decision` markers, so nothing is implemented by guessing (`AGENTS.md` rule 3).
