# product-owner — #226 Node.js version

Source of truth for every decision: [the issue file](../../issues/226-enhance-the-node-client-specs-node-js-version.md) (Solution). Keep the existing style of the spec files (short bullets, `Rejected:` lines, relative links).

## Step 1 — `docs/agents/specs/node-client/packaging.md`

In § Stack › Node.js version:

1. Replace "The specs do not pin a number yet." and the `**Pending decision** (#226)` line with:
   - **Supported range:** `"engines": { "node": ">=22.12.0" }` in both packages; READMEs say "Node.js 22.12 or later". No upper bound; newer majors expected to work, not tested. `engine-strict` not set.
   - **Why 22.12.0:** lowest 22.x where `require(esm)` works unflagged (no experimental warning for modules under `node_modules`); Node.js 20 is end-of-life (April 2026) and unsupported.
   - **API check** table: global `fetch` (18, stable 21), `AbortSignal.timeout` (17.3), `AbortSignal.any` (20.3), `parseArgs` strict (18.3, stable 20), `require(esm)` (22.12.0) — all present on the floor, no alternative needed.
   - **Image versions:** `dockerfiles/vite_pidgey-base/` (`darthjee/node:0.2.1`) runs 22.21.1; the CI image `darthjee/circleci_node:0.2.1` runs 22.14.0. Accepted mismatch (both 22.x, above the floor); the spec follows `darthjee/circleci_node` when it moves.
   - **Raising the floor:** follows the major of `dockerfiles/vite_pidgey-base/`; at the latest when Node.js 22 reaches end of life (30 April 2027) the floor moves to the lowest release of the new major that keeps every API above. A floor raise is breaking: a `0.x` minor with a `CHANGELOG.md` entry (link the breaking list in § Versioning).
   - **Rejected:** `>=22` (22.0–22.11 need `--experimental-require-module`); `>=24` (drops Node 22 users while the repository runs 22).
2. Keep the existing sentence that the version is declared in `engines` and the README, so every JavaScript area runs one Node major.
3. The § Stack bullet "CommonJS consumers use `require(esm)` on a supported Node.js" may link to the new section; do not otherwise change it.

## Step 2 — `testing.md` and the hub

1. `docs/agents/specs/node-client/testing.md`, § Node.js version matrix:
   - replace "The version numbers come from #226." with the concrete jobs: floor on `cimg/node:22.12.0` (Node 22.12.0), current on `darthjee/circleci_node:0.2.1` (Node 22.14.0), linking packaging.md § Node.js version;
   - add that no latest-LTS job is added (the "one job per supported major" rejection stands).
2. Same file, pack smoke test bullet: keep "`require('pidgey-client')` works through `require(esm)`" and remove the sentence "This assertion is dropped, together with the CommonJS claim …, if #226 picks a Node.js version without `require(esm)`."
3. `docs/agents/specs/node-client.md`, § Pending decisions: remove the #226 row. It is the last row, so replace the table with a sentence saying no decision is pending, keeping the intro sentence about markers for future items.
4. Check: `grep -rn "#226" docs/agents/specs/` returns only links that are not pending markers (none expected), and `grep -rn "Pending decision" docs/agents/specs/` returns nothing besides the hub's explanatory sentence.
