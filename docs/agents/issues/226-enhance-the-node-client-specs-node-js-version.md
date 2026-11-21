# Issue: Enhance the Node client specs: Node.js version

## Description

Sub-issue of epic #219: decide the Node.js version of `pidgey-client` (`clients/nodejs/`) and `pidgey-cli` (`clients/cli/`), and record it in `docs/agents/specs/node-client/packaging.md`, replacing its `**Pending decision** (#226)` marker. The decision also fills in the version numbers of the CI matrix that #220 adopted in `testing.md`.

- Parent epic: #219. Builds on #224 (specs written, PR #230 merged) and #220 (matrix shape: floor + current).
- Spec-only: no `package.json`, CI config or Docker image change here.
- Owners: `product-owner` (specs), with `infra` facts for the images.

## Problem

`packaging.md` says the packages use the Node.js version of the frontend images under `dockerfiles/vite_pidgey-base/`, declared in `engines` and the README, but pins no number. Several decisions depend on it:

- The library uses global `fetch`, `AbortSignal`, `AbortSignal.timeout` and `AbortSignal.any`; the CLI uses `node:util` `parseArgs` in strict mode.
- ESM-only publishing assumes CommonJS consumers can use `require(esm)`, which depends on the Node.js version.
- Dropping a Node.js major is already listed as a breaking change of both packages.
- `testing.md` adopts a `clients_checks` matrix over the floor and the current version, but leaves the numbers to this issue.

### Facts

| Source | Node.js |
| --- | --- |
| `dockerfiles/vite_pidgey-base/` (`FROM darthjee/node:0.2.1`), dev and frontend | 22.21.1 |
| `darthjee/circleci_node:0.2.1`, the CI image of the current-version job | 22.14.0 |

| API the specs rely on | Available unflagged since |
| --- | --- |
| global `fetch` | 18 (stable in 21) |
| `AbortSignal.timeout` | 17.3 |
| `AbortSignal.any` | 20.3 |
| `parseArgs` (strict by default) | 18.3 (stable in 20) |
| `require(esm)` | 22.12.0 (no experimental warning for modules under `node_modules`) |

Node.js 20 reached end of life in April 2026; Node.js 22 reaches it on 30 April 2027.

Input from darthjee/navi (issue comment): navi declares no `engines` and runs three different Node 22 patch versions without a matrix; its retrospective recommends declaring `engines.node` and testing the claimed range.

## Expected Behavior

- `packaging.md` § Node.js version states the floor, the image version, the API check and the floor-raise rule (see Solution); the `**Pending decision** (#226)` marker is gone.
- `testing.md` § Node.js version matrix names the concrete versions and images, and the conditional "dropped if #226 picks a version without `require(esm)`" note on the pack smoke test is resolved (the assertion stays).
- The hub `docs/agents/specs/node-client.md` drops the #226 row of its Pending decisions table.
- Spec-only: no `package.json`, `.circleci/config.yml` or Dockerfile change.

## Solution

### Supported range

- Both packages declare `"engines": { "node": ">=22.12.0" }`, and their READMEs state "Node.js 22.12 or later".
- **Why 22.12.0:** the lowest 22.x where every API the specs rely on works without a flag, including `require(esm)`, so the CommonJS claim of `packaging.md` holds on the whole range. Node.js 20 is end-of-life and is not supported.
- **No upper bound.** Newer majors (24, 26, …) are expected to work but are not tested.
- `engine-strict` is not set; `engines` is advisory for consumers, as npm treats it.
- Rejected: `>=22` (22.0–22.11 need `--experimental-require-module`, so the CommonJS claim would need a caveat); `>=24` (drops Node 22 users although the repository itself runs 22).

### API check on the floor

Every API listed in Problem › Facts exists unflagged on 22.12.0. Nothing needs an alternative. The pack smoke test keeps its `require('pidgey-client')` assertion; the conditional note in `testing.md` about dropping it is removed.

### CI matrix (fills in #220's adopted shape)

| Job | Image | Node.js |
| --- | --- | --- |
| `clients_checks`, floor | `cimg/node:22.12.0` | 22.12.0 |
| `clients_checks`, current | `darthjee/circleci_node:0.2.1` | 22.14.0 |

- `clients_integration` and the Codacy upload run on the current job only, as `testing.md` already says.
- No extra job for the latest LTS: #220's rejection of one job per major stands.
- **Recorded mismatch:** the current CI job runs 22.14.0 while dev and the frontend image run 22.21.1. Both are 22.x and above the floor, so this is accepted; when `darthjee/circleci_node` moves, the spec follows it.

### Raising the floor

- The floor follows the major of `dockerfiles/vite_pidgey-base/`. When the image moves to a new major (at the latest when Node.js 22 reaches end of life, 30 April 2027), the floor moves to the lowest release of that major that keeps every API above.
- Raising the floor is a breaking change: released as a `0.x` minor with a `CHANGELOG.md` entry, as `packaging.md`'s breaking list already says.
- A patch-level floor raise inside the same major (for example for a security fix) is not planned; if needed it is treated the same way.

### Files

- `docs/agents/specs/node-client/packaging.md`: § Node.js version as above.
- `docs/agents/specs/node-client/testing.md`: concrete versions in § Node.js version matrix; drop the conditional `require(esm)` note.
- `docs/agents/specs/node-client.md`: remove the #226 row.

## Benefits

- One declared, tested range instead of navi's undeclared mix of Node 22 patch versions.
- CommonJS consumers get `require(esm)` on every supported version, with no caveat.
- The implementation sub-issues can write `engines` and the CI matrix without reopening a decision.
- A clear rule for when and how the floor moves.
