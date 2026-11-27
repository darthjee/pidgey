# Issue: Build the npm publish pipeline of the clients

## Description
Build the CircleCI pipeline that publishes `pidgey-client` and `pidgey-cli` to the public npm registry from per-package tags, as specified in `docs/agents/specs/node-client/packaging.md` (section Publishing). #223 decided the mechanism; this issue builds it. The first real release is #254.

## Problem
The packages pass their checks, but there is no way to publish them to npm.

## Expected Behavior
- A matching tag runs the gate and publishes exactly the tarball the pack job checked; a re-run is a no-op success.
- The npm token is reachable only from the two publish jobs, never from a job running third-party code, and is never echoed.
- A backend tag never publishes to npm, and a client tag never deploys the backend.
- Provenance is either on (`--provenance`) or recorded as a documented gap.
- The publish path can be exercised in dry-run mode before #254 makes the first real release.

## Solution
Implement `docs/agents/specs/node-client/packaging.md`, section Publishing:

1. **Tags and filters**: `pidgey-client-vX.Y.Z` publishes the library, `pidgey-cli-vX.Y.Z` the CLI; each package's jobs run only on its own tag, `branches: ignore: /.*/`; neither matches the backend deploy filter `^\d+\.\d+\.\d+$`.
2. **Jobs** per package:
   - `pack_client` / `pack_cli` (no context): require every check job; frozen install; tag version equals `package.json` `version`; `CHANGELOG.md` has that version; pack (the CLI through the staging-copy script `clients/scripts/pack_cli.sh`); pack check against `pack-files.txt`; persist the tarball to the workspace.
   - `publish_client` / `publish_cli` (context `pidgey-npm`): attach the workspace, install nothing; no-op success when `npm view <pkg>@<version>` already answers; the CLI polls for its library floor (every 10 s, up to 5 min); `npm publish <tarball> --access public --ignore-scripts` (plus `--provenance` if supported); wait until npm serves the new version.
   - Both publish jobs share `serial-group: << pipeline.project.slug >>/pidgey-npm`.
   - No release script uses `set -x`; the token is never echoed.
3. **Dry-run mode**: the publish script accepts a dry-run switch that runs the whole path with `npm publish --dry-run` and skips the wait-for-npm step. The PR demonstrates pack, gate and a dry-run publish (local run or throwaway tag) before #254 does the first real publish.
4. **Provenance**: check whether `npm publish --provenance` works from CircleCI; if it does, enable it. If not, record the gap explicitly in the spec and the docs. Authentication stays the `NPM_TOKEN` in `pidgey-npm` either way: if npm trusted publishing (OIDC) turns out to support CircleCI, open a follow-up issue to switch to it instead of doing it here.
5. **CHANGELOG.md** in `clients/nodejs/` and `clients/cli/`, created as a skeleton (no version entry). The `0.1.0` entries belong to the release PR of #254.
6. **Docs** in `docs/agents/deployment/`: `pipeline.md` (the four jobs, filters, context restriction, serial group, dry-run mode), `variables.md` (`pidgey-npm`, `NPM_TOKEN`), `operations.md` (release procedure, token rotation, deprecation, unpublish).
7. **Maintainer checklist** (manual, outside the PR): create the npm granular token (packages `pidgey-client` and `pidgey-cli`, read and write, with an expiry; confirm the current limits, setting names and the 2FA bypass a CI token needs), create the `pidgey-npm` context with its expression restriction `pipeline.git.tag =~ "^pidgey-(client|cli)-v[0-9]+\.[0-9]+\.[0-9]+$"`, enable 2FA on the account and on both packages.

### Context
- Parent epic: #219 (wave 2, created by #228). Spec: `docs/agents/specs/node-client/packaging.md`.
- Owners: `infra` (CI config, scripts under `clients/scripts/`), `architect` (`docs/agents/deployment/`); the `security` agent reviews the credential setup.
- Depends on: #250, #251, #252 (all closed). Blocks #254.
