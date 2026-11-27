# Packaging

Spec of the two npm packages: names, layout, stack, ownership, versioning, compatibility and
publishing.

## Packages

| Folder | npm package | Exposes | Registry |
| --- | --- | --- | --- |
| `clients/nodejs/` | `pidgey-client` | The library: `PidgeyClient`, `PidgeyError` ([library.md](library.md)). | Public npm |
| `clients/cli/` | `pidgey-cli` | A `bin` entry exposing the global command `pidgey` ([cli.md](cli.md)). | Public npm |

- **Two independent packages, no workspace tooling** at the repository root. The root keeps
  having no `package.json`, so `make tests` remains the single command that runs every check.
  A root workspace was rejected because it would add a root Node project competing with
  `make tests`.
- **Two packages, not one:** a single package carrying the library and the `pidgey` bin was
  rejected, because library consumers would install CLI code they do not use; two packages
  keep ownership and releases separate.
- **Names.** Both are unscoped and were free on npm when checked. The unscoped name `pidgey`
  belongs to an unrelated library (an event emitter with no `bin`), so only the CLI's package
  name differs from its command; the command does not clash.
- **Name reservation.** Both names are to be reserved with manual `0.0.0` placeholders (a
  README pointing at this repository, no code, no `bin`), published by the maintainer with the
  npm credential, outside CI (#227).
  - **Decided, not done yet.** On 2026-11-27, `npm view pidgey-client` and
    `npm view pidgey-cli` both answer `404`, although #227 is closed: neither name is reserved.
    Publishing the placeholders is a prerequisite of the first release (#254), part of the
    [npm publishing setup](../../deployment/operations.md#set-up-npm-publishing).
  - **Placeholder fields.** The placeholder's `package.json` has `name`, version `0.0.0`,
    `description`, `repository` (this repository) and `license` `MIT`, with no `main`, `bin`,
    dependencies or scripts.
  - **Locked at reservation.** Right after each `0.0.0` is published, the package's
    publishing settings are set as in [Credential](#credential-the-pidgey-npm-context): 2FA
    required, allowing only 2FA or a granular token.
  - **Deprecated after `0.1.0`.** Once a package's first real release is on npm, the
    maintainer runs `npm deprecate <pkg>@0.0.0 "placeholder; use >=0.1.0"` from their machine
    with 2FA, never from CI, as in [Rollback](#rollback).
- **Not consumed by `frontend/`.** The admin UI keeps its own cookie-authenticated client
  layer: the client is Bearer-token only, and mixing credentials on the admin UI's surface
  would break the disjoint-credentials rule.

### CLI to library dependency

During development `pidgey-cli` depends on the library by path (`file:../nodejs`), which
keeps the CLI buildable without a workspace. A published CLI release depends on a published
`pidgey-client` version instead.

- **Range.** A published `pidgey-cli` declares `"pidgey-client": "^x.y.z"`.
  - **Why:** the CLI is a thin layer, and all HTTP and retry logic lives in the library, so
    library patch fixes reach CLI users without a CLI release. The library has zero runtime
    dependencies and only this project publishes it, so the range pulls in nothing but our own
    releases.
- **The floor is derived, never bumped by hand.** `x.y.z` is the library version on the
  branch being released, read from `clients/nodejs/package.json`. There is no hand-edited
  floor field.
  - **Rejected:** a hand-edited floor, one more manual step that can drift from the real
    library version.
- **On `0.x`.** `^0.1.0` means `>=0.1.0 <0.2.0`: a library minor, which is breaking on `0.x`,
  is not accepted. It needs a CLI release that raises the floor.
- **From `1.0.0`.**
  - The caret stays: `^1.y.z` also accepts library minors, which are additive.
  - A CLI release raises the floor when it starts using a newer library feature.
  - A library major needs a CLI release.
- **Rejected ranges:**
  - **Exact pin:** every library fix would need a CLI release. npm ignores the CLI's lockfile
    for consumers, so the pin buys little reproducibility.
  - **Tilde:** identical to caret on `0.x`; after `1.0` it would force a CLI release for
    every additive library minor.
  - **Lockstep versions:** couples two independent release cycles and forces empty CLI
    releases.
- **Swap at release time: staging-copy pack script.**
  - The committed `clients/cli/package.json` keeps `file:../nodejs`. A committed `^0.1.0`
    could not install before `0.1.0` is on npm, and it would need `yarn link` or resolutions
    in development and CI.
  - A script in `clients/scripts/` (owner `infra`):
    1. copies `clients/cli/` into a temporary directory;
    2. rewrites `dependencies["pidgey-client"]` to `^<floor>`, the version read from
       `clients/nodejs/package.json`;
    3. normalizes the file modes of the copy, so the packed bytes do not depend on the umask
       of the checkout ([publish script](#publish-script));
    4. runs `npm pack` there.
  - The working tree is never modified. The pack smoke test
    ([testing.md](testing.md#pack-smoke-test-adopted)) and both release jobs use this same
    script, so CI tests the exact tarball shape that gets published.
  - **Rejected:** a `prepack`/`postpack` hook rewriting the committed file in place, because a
    failed pack leaves it dirty.
- **Release order: library first.**
  - A release that changes both packages publishes `pidgey-client` first, then `pidgey-cli`.
  - A CLI-only release keeps the current library version as its floor.
  - **Registry check:** before a CLI publish, `npm view pidgey-client@<floor>` must succeed,
    or the publish aborts. It catches a library version bumped in the repository but never
    published. The check **waits**: it polls up to 5 minutes before aborting
    ([Both packages in one release](#both-packages-in-one-release)). It runs at publish time
    only, never in the smoke test.
- **Edge cases.**
  - **A library patch breaks the CLI.** Every CLI install accepts it through the caret, so
    the fix goes forward: a library patch release reverts or fixes the change, and the bad
    version is deprecated through the [Rollback](#rollback) procedure. There are no range exclusions
    in the CLI (for example `^0.1.0 !0.1.3`) and no emergency CLI pin.
  - **No backports on `0.x`.** A CLI still on `^0.1.x` gets no fixes made in `0.2.x`; users
    upgrade the CLI.
- **What the publish procedure must do.** The procedure in [Publishing](#publishing) applies
  library-first order, the staging-copy pack and the registry check. The mechanism, tags,
  credential, gate and rollback are specified there.

## Stack

- **Plain JavaScript, ESM only:** `"type": "module"`, an `exports` map, no CommonJS build.
  CommonJS consumers use `require(esm)` on a supported Node.js (see [Node.js version](#nodejs-version)). No TypeScript source and no
  build step for the library code; the only pack-time step generates the `.d.ts`
  declarations ([openapi.md](openapi.md#generated-types)).
- **Zero runtime dependencies** for the library: the Node.js standard library only (`fetch`,
  `AbortSignal`), so a consumer does not inherit a dependency tree. HTTP libraries
  (`axios`, `got`, `node-fetch`) were rejected.
- The CLI parses its flags with `node:util` `parseArgs` in strict mode; `commander` /
  `yargs` were rejected.
- **Tooling:** Yarn, Jasmine, `c8` for coverage (100 % threshold, see [testing.md](testing.md#coverage-threshold-adopted)), ESLint 9 flat config: the same as
  `frontend/` ([frontend/stack.md](../../frontend/stack.md)).
- **Code style:** the repository's JavaScript rules apply: files declare one module, the
  entrypoint is the only script, public exports carry accurate JSDoc.
- **Rejected:** TypeScript source (types are delivered as generated `.d.ts`); a dual ESM +
  CommonJS build (needs a build step).

### Node.js version

The Node.js version is the one used by the frontend images under
`dockerfiles/vite_pidgey-base/`, declared once per package in `package.json` (`engines`) and
in the package README, so every JavaScript area runs one Node major.

- **Supported range:** `"engines": { "node": ">=22.12.0" }` in both packages; the READMEs say
  "Node.js 22.12 or later". No upper bound: newer majors are expected to work but are not
  tested. `engine-strict` is not set.
- **Why 22.12.0:** the lowest 22.x where `require(esm)` works unflagged (no experimental
  warning for modules under `node_modules`). Node.js 20 is end-of-life (April 2026) and
  unsupported.
- **API check:** every API the specs rely on exists on the floor, so no alternative is needed.

  | API | Available since |
  | --- | --- |
  | Global `fetch` | 18 (stable in 21) |
  | `AbortSignal.timeout` | 17.3 |
  | `AbortSignal.any` | 20.3 |
  | `parseArgs` (strict mode) | 18.3 (stable in 20) |
  | `require(esm)` | 22.12.0 |

- **Image versions:** `dockerfiles/vite_pidgey-base/` (`darthjee/node:0.2.1`) runs 22.21.1;
  the CI image `darthjee/circleci_node:0.2.1` runs 22.14.0. Accepted mismatch (both 22.x,
  above the floor); the spec follows `darthjee/circleci_node` when it moves.
- **Raising the floor:** follows the major of `dockerfiles/vite_pidgey-base/`; at the latest
  when Node.js 22 reaches end of life (30 April 2027) the floor moves to the lowest release
  of the new major that keeps every API above. A floor raise is breaking: a `0.x` minor with a
  `CHANGELOG.md` entry (see [What is breaking](#what-is-breaking)).
- **Rejected:** `>=22` (22.0 to 22.11 need `--experimental-require-module`); `>=24` (drops
  Node.js 22 users while the repository runs 22).

## Ownership

| Path | Owner |
| --- | --- |
| `clients/nodejs/` (source, Jasmine specs, `package.json`, `yarn.lock`, `eslint.config.mjs`, the package README) | New agent `nodejs` |
| `clients/cli/` (`bin/`, source, Jasmine specs, `package.json`, `yarn.lock`, `eslint.config.mjs`) | New agent `cli` |
| `clients/scripts/` (the shared check and integration scripts, called by the `Makefile` and CI, and the release scripts, called by CI; `nodejs` and `cli` own the npm scripts they call) | `infra` |
| `docs/agents/clients.md` and `docs/agents/clients/` (as-built docs) | `product-owner` |
| `.claude/agents/`, `AGENTS.md`, `docs/agents/folder-structure.md` | `architect` |

- The agents are created by a later sub-issue of #219: `architect.md` gains both rows in its
  agents table and names `clients/` in the ownership split, so client work is never routed
  to `backend` or `frontend`; `folder-structure.md` gains a `clients/` row per package;
  `AGENTS.md` gains the `Clients` documentation row.
- The agent is named `cli`, like its folder `clients/cli/`, so the ownership rule is
  unambiguous. The library folder `clients/nodejs/` names the runtime and leaves room for
  other runtimes under `clients/`.

## Versioning

- **First release `0.1.0`** for both packages, after the `0.0.0` placeholders.
- While on `0.x`, semver's `0.x` rules apply: a breaking change bumps the **minor**
  (`0.1.x` → `0.2.0`), everything else bumps the patch. Moving to `1.0.0` is a later,
  explicit decision.
- Each package keeps a `CHANGELOG.md`. A release has a `## X.Y.Z` heading (optionally
  `## X.Y.Z - YYYY-MM-DD`), which the [pre-publish gate](#pre-publish-gate) reads. The file
  does not ship in the tarball.
- Package versions are independent from each other and from the backend version. The only
  link is the CLI's dependency range, whose floor rule is in
  [CLI to library dependency](#cli-to-library-dependency): a CLI release raises the floor
  when it needs a newer library.

### What is breaking

| Package | Breaking |
| --- | --- |
| Library | Removing or renaming an export, an option or a `PidgeyError` field; changing a default (`maxRetries`, `retryAmbiguous`, `timeoutMs`, `retryDelayMs`); making the retry table retry **more** cases; changing a client-local code; narrowing or removing the loopback exemption for `http://`; dropping a Node.js major. |
| CLI | Removing or renaming a flag; changing an exit code or its meaning; changing what is printed on success; lowering the stdin cap; dropping a Node.js major. |

Not breaking: raising the floor of the CLI's `pidgey-client` range; a new library export
(such as `VERSION`); a new option, flag or `PidgeyError` field; a new client-local code (the docs
state that codes may be added); a case that becomes **not** retried; a new opt-in (such as an environment variable for
`--allow-insecure-http`, or a `--max-body-size` flag); documentation.

## Compatibility

- The README states that the client works with **any Pidgey serving `POST /v1/messages`**.
  The client never calls `GET /v1/openapi.json` at runtime, so servers older than that route
  are supported.
- The client always sends an `Idempotency-Key` header
  ([library.md](library.md#idempotency-key)). A Pidgey predating it ignores the header, so the
  promise above holds; only the duplicate-free retry needs a Pidgey that supports keys.
- The client tolerates API growth
  ([errors-and-retry.md](errors-and-retry.md#forward-compatibility)); a new request field
  needs a client release, because the request payload is a whitelist
  ([library.md](library.md#message)).
- `/v1` itself is additive-only, enforced in backend CI
  ([openapi.md](openapi.md#v1-is-additive-only)).

## Publishing

- Both packages are published to the public npm registry within the epic: `pidgey-client`
  `0.1.0` first, then `pidgey-cli` `0.1.0` depending on it.
- **Who can publish:** whoever can push a package tag (today only the owner), the same access
  model as the backend deploy
  ([pipeline.md](../../deployment/pipeline.md#who-can-publish)). The credential sits in a
  CircleCI context `pidgey-npm` with a **project restriction** and an **expression
  restriction**, not a security group: security groups are not available on the CircleCI free
  plan ([pipeline.md](../../deployment/pipeline.md#context-restriction)).
- npm credentials are never committed and never provided as repository secrets in a pull
  request; where the credential lives is documented in `docs/agents/deployment/` (owned by
  `architect`).

### Mechanism: CircleCI on a per-package tag

- **Tags:** `pidgey-client-vX.Y.Z` publishes the library, `pidgey-cli-vX.Y.Z` publishes the
  CLI. Neither matches the backend deploy filter `^\d+\.\d+\.\d+$`, so a client release never
  deploys the backend, and a backend tag never publishes to npm.
- **Filters:** each package's jobs run only on its own tag
  (`tags: only: /^pidgey-client-v\d+\.\d+\.\d+$/`, resp. `pidgey-cli-v…`), with
  `branches: ignore: /.*/`. The check jobs already run on every tag (`/.*/`), so they gate the
  publish.
- **Rejected options:**

| Option | Why rejected |
| --- | --- |
| CircleCI, navi model (one `X.Y.Z` tag releases every package whose version is not on npm) | A client-only release would need a backend tag, and so a backend deploy. Its idempotent "skip if already on npm" and "wait for npm" ideas are kept. |
| Manual `npm publish` from the maintainer's laptop | No automated pre-publish gate, and the tarball is built on a laptop instead of from a tagged commit. |
| GitHub Actions with trusted publishing (OIDC) | A second CI system next to CircleCI, for one job. |

### CI jobs, per package

Following the backend's split
([pipeline.md](../../deployment/pipeline.md#build-jobs-and-context-jobs)): third-party code
never runs next to the npm token.

| Job | Context | What it does |
| --- | --- | --- |
| `pack_client` / `pack_cli` | None | Requires every check job. Checks the tag version equals `package.json` `version`; checks `CHANGELOG.md` has an entry for that version; frozen install with `--ignore-scripts`, so no dependency lifecycle script runs; for the library, builds the `.d.ts` declarations; packs (the CLI through the [staging-copy script](#cli-to-library-dependency)); runs the pack check against the expected file list; persists the tarball to the workspace. |
| `publish_client` / `publish_cli` | `pidgey-npm` | Requires its pack job. Checks out the repository, attaches the workspace, installs nothing, and runs the [publish script](#publish-script) on the pack job's tarball. |

- The publish jobs share `serial-group: << pipeline.project.slug >>/pidgey-npm`, so publishes
  run one at a time.
- The scripts, their order of checks and the executor are described in
  [pipeline.md](../../deployment/pipeline.md#npm-publish-chain).

### Publish script

`clients/scripts/release_publish.sh [--dry-run] <tag> <tarball>`:

1. takes the package and the version from the **tag**; the `package.json` of the checkout must
   have that version;
2. packs a **reference** tarball from the checkout, the way the pack job does. Only the
   library's `dist/` (build output, not in git) comes from the pack job's tarball;
3. before anything is asked of or sent to the registry, requires the reference to match the
   expected file list and to be **byte-identical** to the pack job's tarball;
4. if `npm view <pkg>@<version>` already answers, ends as a **no-op success**, so a failed
   workflow can be re-run;
5. for the CLI, waits for the floor ([below](#both-packages-in-one-release));
6. publishes those identical bytes with
   `npm publish <tarball> --access public --ignore-scripts`, without `--provenance`
   ([Provenance](#provenance));
7. waits until npm serves the new version.

- **What is published.** The published bytes are the ones the pack job built and checked. The
  second pack is only a comparison: if the two differ, nothing is published.
- **Why:** the pack job runs third-party code, so its tarball is not trusted. A security review
  found that `npm publish <tarball>` honours the tarball's `publishConfig`, which could
  redirect the token, and that inspecting the archive with `tar` is not reliable. Equality with
  a tarball built from the checkout settles both. The content of the library's `dist/`
  declarations is the one part this does not verify
  ([known-gaps.md](../../deployment/known-gaps.md#npm-type-declarations-are-not-verified-at-publish)).
- **Dry run.** `--dry-run` runs the same path without publishing: the same checks, comparison
  and no-op; `npm publish --dry-run` without a token; a CLI floor missing from npm is only a
  warning; no wait for the new version. CI never passes it; it exercises the publish path
  before a real release
  ([operations.md](../../deployment/operations.md#dry-run-a-client-release-locally)).

### Both packages in one release

**Decided in #253:** one tag at a time, library first. The maintainer pushes the library tag,
and pushes the CLI tag only after `publish_client` succeeded. Pushing both tags at once is not
the procedure.

- **Why:** the publish jobs share one serial group, which stays as #253 specifies. CircleCI
  skips a job that joins a busy serial group with a lower pipeline number than the job waiting
  or running, and `publish_cli` holds the group while it waits for the library. With both tags
  pushed at once, the library publish can be skipped, or run only after the CLI has given up
  ([known-gaps.md](../../deployment/known-gaps.md#releasing-both-clients-at-once)).
- `publish_cli` **polls** `npm view pidgey-client@<floor>` every 10 s for up to 5 minutes, and
  aborts if the library version never shows up. This refines the registry check of
  [CLI to library dependency](#cli-to-library-dependency): the check is the same, it just waits
  for npm propagation.
- A CLI-only release keeps the current library version as its floor, which is already on npm,
  so the wait ends at once.

### Credential: the `pidgey-npm` context

- A **new** CircleCI context `pidgey-npm`, holding only `NPM_TOKEN`, separate from
  `pidgey-deploy`, so the SSH deploy key and the npm token are never reachable from the same
  job.
- **Restrictions** in the CircleCI organization settings, no security group
  ([pipeline.md](../../deployment/pipeline.md#context-restriction)):
  - a **project restriction** to the Pidgey project, since otherwise every project of the
    organization can use the context;
  - an **expression restriction**, so only client release tags get the token and an SSH rerun
    does not:
    `pipeline.git.tag =~ "^pidgey-(client|cli)-v[0-9]+\.[0-9]+\.[0-9]+$" and not job.ssh.enabled`.
- Attached only to the two publish jobs. Never a project variable; "Pass secrets to builds
  from forked pull requests" stays off.
- **Token:** an npm **granular** access token, read and write, limited to the packages
  `pidgey-client` and `pidgey-cli`, with an expiry date.
  - **Settings:** "Read and write (publish and stage)", "Only select packages and scopes", and
    the "Bypass two-factor authentication" checkbox checked, since CI cannot answer a 2FA
    prompt
    ([creating and viewing access tokens](https://docs.npmjs.com/creating-and-viewing-access-tokens)).
    The full table is in [variables.md](../../deployment/variables.md#npm-context).
  - **Lifetime:** npm announced a 90-day maximum and a 7-day default for write tokens
    ([September 2025](https://github.blog/changelog/2025-09-29-strengthening-npm-security-important-changes-to-authentication-and-token-management/)).
    The current token pages state neither.
- **2FA:** required on the maintainer's npm account and in both packages' publishing
  settings, allowing only 2FA or a granular token.
  - The exact name of the option under a package's "Publishing access" that allows a token
    bypassing 2FA could **not be verified**. What is known: "Require two-factor authentication
    and disallow tokens" must not be chosen
    ([trusted publishers](https://docs.npmjs.com/trusted-publishers)).
- **Time limit:** npm announces that direct publishing with granular tokens is removed in
  **January 2027** ([about access tokens](https://docs.npmjs.com/about-access-tokens), read
  2026-11-27). The publish jobs stop working then, and rotating the token does not help. The
  switch to trusted publishing is the follow-up #266
  ([known-gaps.md](../../deployment/known-gaps.md#npm-token-publishing-ends-in-january-2027)).
- **Rotation:** a new token with the same settings replaces `NPM_TOKEN`, then the old one is
  revoked ([operations.md](../../deployment/operations.md#rotate-the-npm-token)). An expired or
  revoked token shows up as a `401`/`403` in the publish job: rotate, then re-run the job (it
  is idempotent).
- The token is never echoed; no release script uses `set -x`.

### Provenance

**Outcome (#253): the packages are published without provenance attestations**, so a consumer
cannot verify that a tarball was built from a commit of this repository. Authentication stays
the `NPM_TOKEN` of `pidgey-npm`.

- `npm publish --provenance` supports only GitHub Actions and GitLab CI/CD
  ([generating provenance statements](https://docs.npmjs.com/generating-provenance-statements)).
- npm trusted publishing (OIDC) supports CircleCI cloud, but generates no provenance there
  ([trusted publishers](https://docs.npmjs.com/trusted-publishers)). Switching to it is #266,
  which replaces the stored token but does **not** close the provenance gap.
- The goal stays: the gap is recorded in
  [known-gaps.md](../../deployment/known-gaps.md#npm-packages-published-without-provenance) and
  closes when npm supports provenance from CircleCI.

### Pre-publish gate

A publish happens only if all of these hold:

1. every check job of the pipeline passed (backend, frontend and client checks, including the
   pack smoke test, [testing.md](testing.md#pack-smoke-test-adopted));
2. **bound to the tag:** the package and the version are the ones the tag names, never read
   from the tarball, and that version equals the package's `package.json` `version` (checked
   by both release jobs);
3. `CHANGELOG.md` has an entry for that version;
4. the pack check passes for the tarball being published;
5. **byte comparison:** the pack job's tarball is byte-identical to the one the publish job
   packs from the checkout ([publish script](#publish-script));
6. for the CLI: the floor is on npm (the wait above).

A version already on npm is not an error but a no-op, as described above.

### Release procedure

1. **Release PR:** bumps `version` in the package's `package.json` and adds its `CHANGELOG.md`
   entry. A release changing both packages bumps both in the same PR.
2. After merge, the maintainer tags the merge commit on `main` and pushes **one tag**:
   `pidgey-client-vX.Y.Z`, or `pidgey-cli-vX.Y.Z` for a CLI-only release.
3. CI runs the gate and publishes.
4. When both packages are released, only after `publish_client` succeeded, the maintainer
   tags and pushes `pidgey-cli-vX.Y.Z` ([why](#both-packages-in-one-release)).

The runbook is in [operations.md](../../deployment/operations.md#release-a-client-package).
Before the first release, the maintainer does the
[npm publishing setup](../../deployment/operations.md#set-up-npm-publishing), placeholders
included.

### Rollback

- **No unpublish by default.** A bad release is handled by:
  1. `npm deprecate <pkg>@<version> "<reason>; use <fixed version>"`, run by the maintainer
     from their machine with 2FA (never from CI);
  2. a fix-forward patch release, which also moves the `latest` dist-tag.
- **Unpublish** only to remove a leaked secret, within npm's 72-hour window, together with
  rotating that secret.
- The CLI's caret range means a fixed library patch reaches every CLI install without a CLI
  release ([CLI to library dependency](#cli-to-library-dependency)).
- No dist-tag juggling (`next`, `beta`) and no pre-releases on `0.x`.

### Deployment docs

The pipeline as built (#253) is documented in `docs/agents/deployment/` (owned by
`architect`):

- [pipeline.md](../../deployment/pipeline.md#npm-publish-chain): the four jobs, their filters,
  the release scripts and the dry run; the
  [context restriction](../../deployment/pipeline.md#context-restriction) and the
  [serial group](../../deployment/pipeline.md#serial-group);
- [variables.md](../../deployment/variables.md#npm-context): the `pidgey-npm` context,
  `NPM_TOKEN` and the token settings;
- [operations.md](../../deployment/operations.md#set-up-npm-publishing): setup, release, dry
  run, rerun, token rotation, deprecation and unpublish;
- [known-gaps.md](../../deployment/known-gaps.md): what the publishing does not cover yet.

### Supply chain

The approach follows darthjee/navi's `navi-hey-client` package (a `files` allowlist), adding
the pack check and the hardened publishing token that navi lacks.

- **What ships.** Each package declares a `files` allowlist in `package.json`; there is no
  `.npmignore`. devDependencies (`openapi-typescript`, `typescript`, the contract-test schema
  validator, ESLint, Jasmine, c8) are never installed by consumers. Specs, fixtures, the
  `types/` sources, coverage and lint config never enter the tarball; the generated `.d.ts`
  declarations are in the allowlist ([openapi.md](openapi.md#generated-types)).
- **Pack check.** CI runs `npm pack --dry-run` for each package and compares its file list
  against an **explicit expected file list** committed per package, failing on any added or
  missing file. Comparing against the `files` allowlist alone could never fail, since
  `npm pack` only ever packs what the allowlist matches; the expected list catches an
  allowlist glob that is too broad. The fuller pack smoke test (install the packed tarballs, run
  `pidgey --version` and `--help`, import through the `exports` map, type-check the `.d.ts`)
  runs on every build and is specified in
  [testing.md](testing.md#pack-smoke-test-adopted) and
  [testing.md](testing.md#dts-type-check-adopted). At release, the same expected list is
  compared with the files of the packed tarball itself.
- **No install scripts.** Neither package declares `preinstall`, `install` or `postinstall`
  scripts: installing them runs no code.
- **2FA and token.** 2FA is required on the maintainer's npm account and in each package's
  publishing settings. CI publishes with a **granular** npm token limited to `pidgey-client`
  and `pidgey-cli`, with an expiry date, never a classic long-lived automation token. Where
  the token lives, its rotation and the release procedure are in
  [Credential](#credential-the-pidgey-npm-context) and [Release procedure](#release-procedure).
- **Provenance.** The goal is provenance-attested releases. It is not met: npm generates no
  provenance from CircleCI, so releases are published without it
  ([Provenance](#provenance)).
- **Committed-file lint.** `clients/scripts/checks.sh` fails if the committed
  `clients/cli/package.json` declares `pidgey-client` as anything other than `file:../nodejs`,
  so the published range only ever comes from the staging-copy pack
  ([CLI to library dependency](#cli-to-library-dependency)).
- **Range versus pin.** The range admits only this project's own releases, published under
  the token, 2FA and gate rules above and in [Publishing](#publishing). The one added risk is
  that a compromised `pidgey-client` publish reaches CLI installs without a CLI release; a pin
  would not protect library consumers from the same publish, so the publishing controls remain
  the defence.
- **Frozen installs.** CI installs with the committed lockfile frozen
  (`yarn install --frozen-lockfile`, matching the Yarn tooling in [Stack](#stack)), so a
  release never resolves new dependency versions. The release pack install also passes
  `--ignore-scripts`, so no dependency lifecycle script runs in it.
