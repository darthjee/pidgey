# Product-owner Plan: Release pidgey-client and pidgey-cli 0.1.0

Main plan: [plan.md](plan.md)

## Shared contracts

- The first release, `0.1.0`, of both packages is a **one-off manual publish** from the maintainer's machine, of tarballs produced by `clients/scripts/release_pack.sh` and checked by `release_publish.sh --dry-run`. CI publishing (`publish_client`, `publish_cli`) starts with the next release, after #266 (trusted publishing).
- Reasons, to state in the docs:
  - npm removes direct publishing with a granular token in January 2027;
  - npm trusted publishing needs the packages to already exist;
  - neither name exists on npm (404 on 2026-11-27).
- No `0.0.0` placeholders are published, and none are deprecated: `0.1.0` reserves both names.
- No release tag is pushed until #266 lands. A pushed tag would start `publish_*`, which fails without the `pidgey-npm` context.

## Implementation Steps

### Step 1 — Packaging spec

In `docs/agents/specs/node-client/packaging.md`:

- **Name reservation:** replace the placeholder plan with the outcome. The names are claimed by the `0.1.0` manual publish (#254), and the placeholders are dropped. Keep the publishing-settings lock (2FA, set right after the first publish) and remove the "Deprecated after `0.1.0`" step.
- **Release procedure:** add a "First release (`0.1.0`)" exception with the reasons above, linking to the new `operations.md` section. State that the CI procedure applies from the first release after #266.
- **Rejected-mechanisms table:** the "Manual `npm publish`" row stays rejected as the release mechanism. Note that the first release uses it once, with the scripted gate and the byte comparison run locally.

### Step 2 — Deployment docs

- In `docs/agents/deployment/operations.md`:
  - add a section "Publish the first client release manually", with the runbook from [plan.md](plan.md#shared-contracts): library first, publish the exact tarball `release_pack.sh` produced, `npm view` checks (version, `dependencies` of the CLI, `latest` dist-tag), `npm install -g pidgey-cli`, no tag push until #266;
  - in "Set up npm publishing", mark the placeholder step as no longer needed and the token and context steps as superseded by #266;
  - in "Deprecate a client release", drop the placeholder sentence.
- In `docs/agents/deployment/known-gaps.md`:
  - *npm publishing not set up or exercised yet*: the first release is a manual publish (#254), and the CI publish path stays unexercised until #266;
  - *npm token publishing ends in January 2027*: the token path is no longer planned for the first release, and CI publishing depends on #266.

## Files to Change

- `docs/agents/specs/node-client/packaging.md`: name reservation outcome, first-release exception.
- `docs/agents/deployment/operations.md`: manual first-release runbook, setup and deprecation updates.
- `docs/agents/deployment/known-gaps.md`: the gaps reworded for the manual first release.

## Notes

- After the publish, a small follow-up PR states in `known-gaps.md` that both packages exist on npm at `0.1.0`. That PR is outside this plan.
- Keep anchors that other docs link to (`#set-up-npm-publishing`, `#release-a-client-package`, `#release-procedure`) stable, or update the links.
