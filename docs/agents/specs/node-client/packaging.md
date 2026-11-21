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
- **Name reservation.** Both names are reserved with manual `0.0.0` placeholders (a README
  pointing at this repository, no code, no `bin`), published by the maintainer with the npm
  credential, outside CI (#227).
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
    3. runs `npm pack` there.
  - The working tree is never modified. The pack smoke test
    ([testing.md](testing.md#pack-smoke-test-adopted)) and the publish step use this same
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
| `clients/scripts/` (the shared check and integration scripts, called by the `Makefile` and CI; `nodejs` and `cli` own the npm scripts they call) | `infra` |
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
- Each package keeps a `CHANGELOG.md`.
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
  model as the backend deploy. The credential sits in a CircleCI context `pidgey-npm` with an
  **expression restriction**, not a security group: security groups are not available on the
  CircleCI free plan
  ([pipeline.md](../../deployment/pipeline.md#context-restriction)).
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
| `pack_client` / `pack_cli` | None | Requires every check job. Frozen install; checks the tag version equals `package.json` `version`; checks `CHANGELOG.md` has an entry for that version; packs (the CLI through the [staging-copy script](#cli-to-library-dependency)); runs the pack check against the expected file list; persists the tarball to the workspace. |
| `publish_client` / `publish_cli` | `pidgey-npm` | Requires its pack job. Attaches the workspace and installs nothing. Then: (1) if `npm view <pkg>@<version>` already answers, ends as a **no-op success**, so a failed workflow can be re-run; (2) for the CLI, waits for the floor ([below](#both-packages-in-one-release)); (3) `npm publish <tarball> --access public --ignore-scripts`, with `--provenance` when supported; (4) waits until npm serves the new version. |

- The publish jobs share `serial-group: << pipeline.project.slug >>/pidgey-npm`, so publishes
  run one at a time.
- `npm publish <tarball>` publishes the tarball built and checked by the pack job; it never
  re-packs.

### Both packages in one release

The maintainer pushes both tags; the two pipelines may run in parallel.

- `publish_cli` **polls** `npm view pidgey-client@<floor>` every 10 s for up to 5 minutes, and
  aborts if the library version never shows up. This refines the registry check of
  [CLI to library dependency](#cli-to-library-dependency): the check is the same, it just waits
  for npm propagation and for a library pipeline running at the same time.
- A CLI-only release keeps the current library version as its floor, which is already on npm,
  so the wait ends at once.

### Credential: the `pidgey-npm` context

- A **new** CircleCI context `pidgey-npm`, holding only `NPM_TOKEN`, separate from
  `pidgey-deploy`, so the SSH deploy key and the npm token are never reachable from the same
  job.
- **Expression restriction** in the CircleCI organization settings:
  `pipeline.git.tag =~ "^pidgey-(client|cli)-v[0-9]+\.[0-9]+\.[0-9]+$"`. No security group.
- Attached only to the two publish jobs. Never a project variable; "Pass secrets to builds
  from forked pull requests" stays off.
- **Token:** an npm **granular** access token, read and write, limited to the packages
  `pidgey-client` and `pidgey-cli`, with an expiry date. npm caps write tokens at 90 days.
  - **To confirm** (implementation sub-issue): the current cap, the exact setting names, and
    the 2FA bypass a CI token needs.
- **2FA:** required on the maintainer's npm account and in both packages' publishing
  settings, allowing only 2FA or a granular token.
- **Rotation** (documented in `operations.md`):
  1. create a new token with the same settings;
  2. replace `NPM_TOKEN` in `pidgey-npm`;
  3. revoke the old token;
  4. set a reminder before the next expiry.
  An expired or revoked token shows up as a `401`/`403` in the publish job: rotate, then
  re-run the job (it is idempotent).
- The token is never echoed; no release script uses `set -x`.

### Provenance

- Goal kept: `npm publish --provenance`, or npm trusted publishing (OIDC) instead of the
  stored token, if npm supports CircleCI for it.
- **To confirm** (implementation sub-issue): whether npm supports CircleCI for provenance or
  trusted publishing. If it does not, this section records the gap explicitly (published
  without provenance attestations, and why). The goal is not dropped silently.

### Pre-publish gate

A publish happens only if all of these hold:

1. every check job of the pipeline passed (backend, frontend and client checks, including the
   pack smoke test, [testing.md](testing.md#pack-smoke-test-adopted));
2. the tag's version equals the package's `package.json` `version`;
3. `CHANGELOG.md` has an entry for that version;
4. the pack check passes for the tarball being published;
5. for the CLI: the floor is on npm (the wait above).

A version already on npm is not an error but a no-op, as described above.

### Release procedure

1. **Release PR:** bumps `version` in the package's `package.json` and adds its `CHANGELOG.md`
   entry. A release changing both packages bumps both in the same PR.
2. After merge, the maintainer tags the merge commit on `main` (`pidgey-client-vX.Y.Z`, then
   `pidgey-cli-vX.Y.Z` if needed) and pushes the tag(s).
3. CI runs the gate and publishes.

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

### Docs for the implementation sub-issue

The sub-issue that builds the pipeline updates `docs/agents/deployment/` (owned by
`architect`):

- `pipeline.md`: the four jobs, their filters, the context restriction and the serial group;
- `variables.md`: the `pidgey-npm` context and `NPM_TOKEN`;
- `operations.md`: the release procedure, token rotation, deprecation and unpublish.

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
  [testing.md](testing.md#dts-type-check-adopted).
- **No install scripts.** Neither package declares `preinstall`, `install` or `postinstall`
  scripts: installing them runs no code.
- **2FA and token.** 2FA is required on the maintainer's npm account and in each package's
  publishing settings. CI publishes with a **granular** npm token limited to `pidgey-client`
  and `pidgey-cli`, with an expiry date, never a classic long-lived automation token. Where
  the token lives, its rotation and the release procedure are in
  [Credential](#credential-the-pidgey-npm-context) and [Release procedure](#release-procedure).
- **Provenance.** The goal is provenance-attested releases: `npm publish --provenance`, or
  npm trusted publishing (OIDC), whenever CircleCI is supported for it. If it is not, this
  spec records the gap explicitly instead of dropping the goal silently; see
  [Provenance](#provenance).
- **Committed-file lint.** `clients/scripts/checks.sh` fails if the committed
  `clients/cli/package.json` declares `pidgey-client` as anything other than `file:../nodejs`,
  so the published range only ever comes from the staging-copy pack
  ([CLI to library dependency](#cli-to-library-dependency)).
- **Range versus pin.** The range admits only this project's own releases, published under
  the token, 2FA and provenance rules above and in [Publishing](#publishing). The one added risk is that a
  compromised `pidgey-client` publish reaches CLI installs without a CLI release; a pin would
  not protect library consumers from the same publish, so the publishing controls remain the
  defence.
- **Frozen installs.** CI installs with the committed lockfile frozen
  (`yarn install --frozen-lockfile`, matching the Yarn tooling in [Stack](#stack)), so a
  release never resolves new dependency versions.
