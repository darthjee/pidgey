# Packaging, hub and consistency pass

- `packaging.md` Stack: "nyc/c8 for coverage" → "`c8` for coverage (100 % threshold, see testing.md)".
- `packaging.md` Supply chain, "Pack check": replace the sentence saying the fuller smoke test "is not decided here … stays with the extra checks pending decision" with a cross-reference to the adopted smoke test and `.d.ts` check in `testing.md`.
- `packaging.md` Ownership: add a row `clients/scripts/` (the shared check and integration scripts) → `infra`, noting that `nodejs` and `cli` own the npm scripts it calls.
- `docs/agents/specs/node-client.md`: remove the #220 row of the pending table; update the `testing.md` row of the Files table if its summary no longer matches (for example "test approach, spec files per package, extra checks, edge-case rules, scripts, `Makefile` targets and CI jobs").
- Consistency pass: no `#220` reference remains in `docs/agents/specs/` (`grep -rn '#220' docs/agents/specs/`); anchors used in cross-references resolve; the #222, #223 and #226 markers are untouched.

## Files to Change
- `docs/agents/specs/node-client/packaging.md` — Stack, Supply chain, Ownership.
- `docs/agents/specs/node-client.md` — pending table, Files table.
- `docs/agents/specs/node-client/testing.md` — final consistency fixes only.
