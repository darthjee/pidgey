# Issue: Release pidgey-client and pidgey-cli 0.1.0

## Description
Ship the first real release, `0.1.0`, of `pidgey-client` (`clients/nodejs/`) and `pidgey-cli` (`clients/cli/`) as a **one-off manual publish** from the maintainer's machine. The repo's own release scripts still run every check of the release gate. CI publishing starts with the next release, once #266 (npm trusted publishing) lands.

## Problem
- On 2026-11-27, `npm view pidgey-client` and `npm view pidgey-cli` both return 404. Neither name exists on npm, and the `0.0.0` placeholders of #227 were never published.
- The CI publish jobs (#253) use an npm granular token, a publishing path npm removes in **January 2027** (`known-gaps.md`, #266). Setting it up now would mean placeholders by hand, a token, and the `pidgey-npm` context, all for a single release before the path stops working.
- #266 (trusted publishing) needs both packages to already exist on npm before a trusted publisher can be configured.
- Both `package.json` files already say `0.1.0`, but neither `CHANGELOG.md` has a `## 0.1.0` entry, so `release_pack.sh` refuses the release.

## Expected Behavior
- `pidgey-client@0.1.0` and `pidgey-cli@0.1.0` are on the public npm registry, and `latest` points at `0.1.0` for both.
- The published `pidgey-cli@0.1.0` depends on `pidgey-client@^0.1.0`. The staging-copy pack script derives that range; the committed `clients/cli/package.json` keeps `file:../nodejs`.
- `npm install -g pidgey-cli` exposes the `pidgey` command.
- No `0.0.0` placeholders are published, so there is nothing to deprecate: `0.1.0` claims both names.
- The docs record that `0.1.0` was a one-off manual publish, and that CI publishing starts after #266.

## Solution
### 1. Release PR (`nodejs`, `cli`, `architect`)
- Add a bare `## 0.1.0` entry to `clients/nodejs/CHANGELOG.md` and `clients/cli/CHANGELOG.md`: a short *Initial release* summary of each package's features. No version bump (already `0.1.0`) and no hand-edited floor.
- `docs/agents/specs/node-client/packaging.md` (Release procedure) and `docs/agents/deployment/operations.md`:
  - record the first-release exception and its reason (token path ends January 2027; trusted publishing needs existing packages);
  - add the manual-publish runbook below;
  - mark the *Set up npm publishing* token steps as superseded by #266, and the placeholder step as unnecessary.
- Check both packages with the local dry run (*Dry-run a client release locally* in `operations.md`).

### 2. Maintainer: manual publish (after the merge)
From a clean checkout of the merge commit on `main`, with CI green, Node.js 22.12 or later, and 2FA on the npm account:
1. `out="$(mktemp -d)"`, then `clients/scripts/release_pack.sh pidgey-client-v0.1.0 "$out"`. This runs the tag/version, CHANGELOG, frozen install, pack and file-list checks.
2. `clients/scripts/release_publish.sh --dry-run pidgey-client-v0.1.0 "$out/pidgey-client-0.1.0.tgz"`. This adds the byte comparison.
3. `npm publish "$out/pidgey-client-0.1.0.tgz" --access public`, confirmed with 2FA. Publish **that tarball**, never a fresh pack.
4. Check that `npm view pidgey-client@0.1.0 version` answers.
5. Repeat steps 1 to 3 for `pidgey-cli-v0.1.0` / `pidgey-cli-0.1.0.tgz`. The pack prints the `pidgey-client` range, which must be `^0.1.0`.
6. Check `npm view pidgey-cli@0.1.0 version`, `npm view pidgey-cli@0.1.0 dependencies`, the `latest` dist-tag of both packages, and that `npm install -g pidgey-cli` exposes `pidgey`.
7. **Do not push** `pidgey-client-v0.1.0` or `pidgey-cli-v0.1.0` yet. A pushed tag starts `publish_*`, which would fail on the missing `pidgey-npm` context. Push them once #266 lands; the publish job then skips a version already on npm.
8. Record the outcome in this issue: versions, dist-tags, and the absence of provenance attestations (expected; see `known-gaps.md`).

### 3. Follow-up docs PR (`architect`)
After publishing, update `docs/agents/deployment/known-gaps.md`:
- *npm publishing not set up or exercised yet*: the packages exist and `0.1.0` was published manually; the CI publish path is still unexercised until #266;
- *npm token publishing ends in January 2027*: points at #266 only.

### Context
- Parent epic: #219 (wave 2, created by #228). Depends on #253 (closed).
- #266 follows and owns the first CI publish. #229 (remove the spec files) follows.

## Benefits
- Both packages are installable from npm without building a token setup that npm retires in January 2027.
- The packages now exist on npm, which #266 needs before it can configure trusted publishing.
- The release gate (version, CHANGELOG, file list, byte comparison) still guards the manual publish, and the tarball is built from the tagged commit.
