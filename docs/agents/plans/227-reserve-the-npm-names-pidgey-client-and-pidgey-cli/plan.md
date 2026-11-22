# Plan: Reserve the npm names pidgey-client and pidgey-cli

Issue: [227-reserve-the-npm-names-pidgey-client-and-pidgey-cli.md](../../issues/227-reserve-the-npm-names-pidgey-client-and-pidgey-cli.md)

## Overview
Spec-only change: `packaging.md`'s Name reservation bullet records the two rules decided in #227. The first locks each package's publishing settings right after its `0.0.0` is published. The second deprecates `0.0.0` after the package's first real release (`0.1.0`). Publishing the placeholders on npm is a manual maintainer task listed below. No agent runs it.

See [product-owner.md](product-owner.md) for the full plan.

## Manual checklist (maintainer, not an agent)

Done outside the repository and outside CI, ideally before the spec PR merges, since merging it closes #227:

1. Check that 2FA is enabled on the npm account, and that `npm view pidgey-client` and `npm view pidgey-cli` still answer `404`.
2. For each name, build a placeholder in a temporary directory (never committed):
   - `package.json` with `name`, `version` `0.0.0`, a "reserved for the upcoming Pidgey client" `description`, `repository` pointing at this GitHub repository and `license` `MIT`;
   - no `main`, `bin`, dependencies or scripts;
   - a `README.md` saying the name is reserved and linking to this repository.
3. `npm publish --access public` from each directory, confirmed with 2FA.
4. In each package's npm settings, require 2FA and allow only 2FA or a granular token.
5. Verify that `npm view pidgey-client` and `npm view pidgey-cli` show `0.0.0` with the maintainer as the only maintainer.
6. Later, after each package's `0.1.0` is on npm, run `npm deprecate <pkg>@0.0.0 "placeholder; use >=0.1.0"` from the maintainer's machine with 2FA.
