# Issue: Decide how the client packages are published to npm

## Description
Decide and specify how `pidgey-client` and `pidgey-cli` (epic #219) are published to the public npm registry. Record the decision in `docs/agents/specs/node-client/packaging.md`.

## Problem

Epic #219 publishes two packages to the public npm registry: `pidgey-client` (`clients/nodejs/`) and `pidgey-cli` (`clients/cli/`, command `pidgey`). The names are reserved with manual `0.0.0` placeholders (#227) and the first real release is `0.1.0`, but **how** releases are published is still open: `docs/agents/specs/node-client/packaging.md` carries a `**Pending decision** (#223)` marker.

### Already decided (not reopened here)

- Whoever can publish is the same group that can deploy the backend.
- Versions are independent per package and from the backend; `0.x` semver rules; each package keeps a `CHANGELOG.md`.
- #222 (merged): a published CLI declares `"pidgey-client": "^<floor>"`, packed by the staging-copy script in `clients/scripts/`; the library is published first; before a CLI publish, `npm view pidgey-client@<floor>` must succeed; a broken library patch is fixed forward and the bad version deprecated through this issue's rollback procedure.
- #220: the pack check (expected file list) and the pack smoke test run on every build.

### A wording to correct

`packaging.md` says the CI credential "sits in a CircleCI context restricted to the same **security group** as `pidgey-deploy`". `docs/agents/deployment/pipeline.md` states that security groups are not available on the CircleCI free plan: `pidgey-deploy` is restricted by an **expression restriction** on the tag (`pipeline.git.tag =~ "^[0-9]+\.[0-9]+\.[0-9]+$"`). The spec must say what is actually enforced.

### Options considered

| Option | Sketch | Verdict |
| --- | --- | --- |
| **CircleCI on a per-package tag** | `pidgey-client-vX.Y.Z` / `pidgey-cli-vX.Y.Z`, a restricted `pidgey-npm` context, the backend's build-job / context-job split. | **Chosen.** |
| CircleCI, navi model | darthjee/navi: one `X.Y.Z` tag releases every package whose version is not on npm yet. | Rejected: a client-only release would need a backend tag, and so a backend deploy. Its idempotent "skip if already on npm" and "wait for npm" ideas are kept. |
| Manual `npm publish` | The maintainer publishes from a laptop with 2FA, following a checklist. | Rejected: no automated pre-publish gate, and the tarball is built on a laptop instead of from a tagged commit. |
| GitHub Actions + OIDC | Trusted publishing with no stored token and native provenance. | Rejected: a second CI system next to CircleCI, for one job. |

## Expected Behavior

- `packaging.md` replaces its `**Pending decision** (#223)` marker with a precise publishing spec: mechanism, tags, CI jobs, gate, credential, provenance, release and rollback procedure (see Solution).
- It is precise enough for the implementation sub-issue to write `.circleci/config.yml` and update `docs/agents/deployment/pipeline.md`, `variables.md` and `operations.md` (owned by `architect`) without reopening any decision.
- The "security group" wording is replaced by the expression restriction actually used.
- The hub `docs/agents/specs/node-client.md` drops the #223 row of its Pending decisions table; `testing.md`'s "Publish gate: the gate itself belongs to #223" becomes a link to the new section.
- This issue is **spec-only**: no CI config, script, CircleCI context or npm token is created here.

## Solution

Record the decisions below in `docs/agents/specs/node-client/packaging.md` (section Publishing, plus the Supply chain bullets it touches), and the small follow-ups in `node-client.md` and `testing.md`.

### Mechanism: CircleCI on a per-package tag

- **Tags:** `pidgey-client-vX.Y.Z` publishes the library, `pidgey-cli-vX.Y.Z` publishes the CLI. Neither matches the backend deploy filter `^\d+\.\d+\.\d+$`, so a client release never deploys the backend, and a backend tag never publishes to npm.
- **Filters:** each package's jobs run only on its own tag (`tags: only: /^pidgey-client-v\d+\.\d+\.\d+$/`, resp. `pidgey-cli-v…`), with `branches: ignore: /.*/`. The check jobs already run on every tag (`/.*/`), so they gate the publish.
- **Who can publish:** anyone who can push such a tag, today only the owner, the same as the backend deploy.

### CI jobs, per package

Following the backend's split ([pipeline.md](../../deployment/pipeline.md#build-jobs-and-context-jobs)): third-party code never runs next to the npm token.

| Job | Context | What it does |
| --- | --- | --- |
| `pack_client` / `pack_cli` | None | Requires every check job. Frozen install; checks the tag version equals `package.json` `version`; checks `CHANGELOG.md` has an entry for that version; packs (the CLI through the #222 staging-copy script); runs the pack check against the expected file list; persists the tarball to the workspace. |
| `publish_client` / `publish_cli` | `pidgey-npm` | Requires its pack job. Attaches the workspace and installs nothing. Then: (1) if `npm view <pkg>@<version>` already answers, it ends as a **no-op success**, so a failed workflow can be re-run; (2) for the CLI, waits for the floor (below); (3) `npm publish <tarball> --access public --ignore-scripts`, with `--provenance` when supported; (4) waits until npm serves the new version. |

- The publish jobs share `serial-group: << pipeline.project.slug >>/pidgey-npm`, so publishes run one at a time.
- `npm publish <tarball>` publishes the tarball built and checked by the pack job; it never re-packs.

### Both packages in one release

The maintainer pushes both tags; the two pipelines may run in parallel.

- `publish_cli` **polls** `npm view pidgey-client@<floor>` every 10 s for up to 5 minutes, and aborts if the library version never shows up. This refines #222's "must succeed, or the publish aborts" registry check: the check is the same, it just waits for npm propagation and for a library pipeline running at the same time.
- A CLI-only release keeps the current library version as its floor (#222), which is already on npm, so the wait ends at once.

### Credential: the `pidgey-npm` context

- A **new** CircleCI context `pidgey-npm`, holding only `NPM_TOKEN`, separate from `pidgey-deploy`, so the SSH deploy key and the npm token are never reachable from the same job.
- **Expression restriction** in the CircleCI organization settings: `pipeline.git.tag =~ "^pidgey-(client|cli)-v[0-9]+\.[0-9]+\.[0-9]+$"`. No security group (none on the free plan). This replaces the "security group" wording of `packaging.md`.
- Attached only to the two publish jobs. Never a project variable; "Pass secrets to builds from forked pull requests" stays off.
- **Token:** an npm **granular** access token, read and write, limited to the packages `pidgey-client` and `pidgey-cli`, with an expiry date (npm caps write tokens at 90 days; the implementation sub-issue confirms the current limit and the exact setting names, including the 2FA bypass a CI token needs).
- **2FA:** required on the maintainer's npm account and in both packages' publishing settings, allowing only 2FA or a granular token.
- **Rotation** (documented in `operations.md`): create a new token with the same settings, replace `NPM_TOKEN` in `pidgey-npm`, revoke the old one, and set a reminder before the next expiry. An expired or revoked token shows up as a `401`/`403` in the publish job: rotate, then re-run the job (it is idempotent).
- The token is never echoed; no release script uses `set -x`.

### Provenance

- Goal kept: `npm publish --provenance`, or npm trusted publishing (OIDC) instead of the stored token, if npm supports CircleCI for it.
- The implementation sub-issue checks this. If it is not supported, `packaging.md` records the gap explicitly (published without provenance attestations, and why). The goal is not dropped silently.

### Pre-publish gate

A publish happens only if all of these hold:

1. every check job of the pipeline passed (backend, frontend and client checks, including the pack smoke test, #220);
2. the tag's version equals the package's `package.json` `version`;
3. `CHANGELOG.md` has an entry for that version;
4. the pack check passes for the tarball being published;
5. for the CLI: the floor is on npm (the wait above).

A version already on npm is not an error but a no-op, as described above.

### Release procedure

1. **Release PR:** bumps `version` in the package's `package.json` and adds its `CHANGELOG.md` entry. A release changing both packages bumps both in the same PR.
2. After merge, the maintainer tags the merge commit on `main` (`pidgey-client-vX.Y.Z`, then `pidgey-cli-vX.Y.Z` if needed) and pushes the tag(s).
3. CI runs the gate and publishes.

### Rollback

- **No unpublish by default.** A bad release is handled by:
  1. `npm deprecate <pkg>@<version> "<reason>; use <fixed version>"`, run by the maintainer from their machine with 2FA (never from CI);
  2. a fix-forward patch release, which also moves the `latest` dist-tag.
- **Unpublish** only to remove a leaked secret, within npm's 72-hour window, together with rotating that secret.
- The CLI's caret range means a fixed library patch reaches every CLI install without a CLI release (#222).
- No dist-tag juggling (`next`, `beta`) and no pre-releases on `0.x`.

### Docs for the implementation sub-issue

The sub-issue that builds the pipeline updates `docs/agents/deployment/`:

- `pipeline.md`: the four jobs, their filters, the context restriction and the serial group;
- `variables.md`: the `pidgey-npm` context and `NPM_TOKEN`;
- `operations.md`: the release procedure, token rotation, deprecation and unpublish.

## Benefits

- One CI system and the same access model as the backend deploy, with the npm token as isolated as the deploy key.
- A client release never redeploys the backend, and a backend release never publishes to npm.
- What gets published is the exact tarball that passed the pack check and the smoke test, built from a tagged commit.
- Idempotent publish jobs: a failed release workflow can simply be re-run.
- A documented rollback that matches #222's fix-forward rule.
