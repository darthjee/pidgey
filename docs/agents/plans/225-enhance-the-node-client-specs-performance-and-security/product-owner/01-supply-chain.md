# Supply chain in packaging.md

Replace the `**Pending decision** (#225)` marker at the end of `## Publishing` in `packaging.md` with a `### Supply chain` subsection (under `## Publishing`, or a new `## Supply chain` section right after it) stating:

- **What ships:** each package declares a `files` allowlist in `package.json`; no `.npmignore`. devDependencies (`openapi-typescript`, `typescript`, the contract-test schema validator, ESLint, Jasmine, c8) are never installed by consumers; specs, fixtures, the `types/` sources, coverage and lint config never enter the tarball. Keep the generated `.d.ts` in the allowlist (link `openapi.md#generated-types` rather than restating it).
- **Pack check:** CI runs `npm pack --dry-run` per package and fails when the file list has anything outside the allowlist. The fuller smoke test (install the packed tarball, run `pidgey --help`, type-check the `.d.ts`) stays with the #220 marker in `testing.md` — link it, do not decide it.
- **2FA and token:** 2FA required on the maintainer's npm account and in each package's publishing settings; CI publishes with a **granular** npm token limited to `pidgey-client` and `pidgey-cli`, with an expiry date, never a classic long-lived automation token. Where the token lives, rotation and the release procedure stay with #223's marker.
- **Provenance:** the goal is provenance-attested releases (`npm publish --provenance`, or npm trusted publishing / OIDC) whenever the mechanism chosen in #223 supports it; otherwise the spec records the gap explicitly. #223 confirms feasibility.
- **Frozen installs:** CI installs with the committed lockfile frozen (`yarn install --frozen-lockfile`, matching the Yarn tooling in `## Stack`), so a release never resolves new dependency versions.
- Mention briefly that the approach follows darthjee/navi's `files` allowlist, adding the pack check and the hardened token navi lacks (one sentence, no more).

Also add the `npm pack --dry-run` file-list check to the cross-cutting list in `testing.md` (one bullet, linking back to `packaging.md`).

## Files to Change
- `docs/agents/specs/node-client/packaging.md` — supply-chain decisions replace the #225 marker.
- `docs/agents/specs/node-client/testing.md` — one cross-cutting bullet for the pack check.
