# Product Owner Plan: Reserve the npm names pidgey-client and pidgey-cli

Main plan: [plan.md](plan.md)

## Overview
Record in `docs/agents/specs/node-client/packaging.md` the two reservation rules decided in #227. The npm actions themselves are manual and out of scope for this agent (see the checklist in [plan.md](plan.md)).

## Context
- The **Name reservation** bullet in `## Packages` currently reads: "Both names are reserved with manual `0.0.0` placeholders (a README pointing at this repository, no code, no `bin`), published by the maintainer with the npm credential, outside CI (#227)."
- `### Credential: the pidgey-npm context` already says 2FA is required on the maintainer's account and in both packages' publishing settings, allowing only 2FA or a granular token. This plan only adds *when* that setting is applied.
- `### Rollback` already defines `npm deprecate`, run by the maintainer from their machine with 2FA and never from CI. The placeholder deprecation reuses that rule.
- `## Versioning` already states "First release `0.1.0` for both packages, after the `0.0.0` placeholders."

## Implementation Steps

### Step 1 — Extend the Name reservation bullet
In `packaging.md`, turn the **Name reservation** bullet into a bullet with sub-bullets, keeping the existing sentence and adding:
- the placeholder's `package.json` fields: `name`, version `0.0.0`, `description`, `repository` (this repository) and `license` `MIT`, with no `main`, `bin`, dependencies or scripts;
- **Locked at reservation:** right after each `0.0.0` is published, the package's publishing settings are set as in [Credential](#credential-the-pidgey-npm-context): 2FA required, allowing only 2FA or a granular token;
- **Deprecated after `0.1.0`:** once a package's first real release is on npm, the maintainer runs `npm deprecate <pkg>@0.0.0 "placeholder; use >=0.1.0"` from their machine with 2FA, never from CI, as in [Rollback](#rollback).

Keep the file's style: lines wrapped at about 95 columns, bold lead-ins, relative links. Do not edit other sections.

### Step 2 — Consistency check
- `grep -n "0\.0\.0" docs/agents/specs/node-client/packaging.md`: the Versioning line and the new sub-bullets agree (placeholders first, then `0.1.0`).
- Check that the anchors `#credential-the-pidgey-npm-context` and `#rollback` match the actual headings (GitHub slug rules: backticks dropped, spaces become `-`).
- `grep -rn "#227" docs/agents/specs/`: references to #227 elsewhere still read correctly.

## Files to Change
- `docs/agents/specs/node-client/packaging.md`: the Name reservation bullet gains the placeholder fields, the lock-at-reservation rule and the deprecate-after-`0.1.0` rule.

## Notes
- Docs-only: no code, CI or package files change, so no test or lint job is affected.
- Do not state that the names *are* already on npm with a date. The spec describes the rule, and the reservation itself is the maintainer's manual step.
