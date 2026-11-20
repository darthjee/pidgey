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

**Pending decision** (#222): caret range, exact pin or lockstep versions for the published dependency, how `file:../nodejs` is swapped for it at release time, and how that changes at `1.0.0`.

## Stack

- **Plain JavaScript, ESM only:** `"type": "module"`, an `exports` map, no CommonJS build.
  CommonJS consumers use `require(esm)` on a supported Node.js. No TypeScript source and no
  build step for the library code; the only pack-time step generates the `.d.ts`
  declarations ([openapi.md](openapi.md#generated-types)).
- **Zero runtime dependencies** for the library: the Node.js standard library only (`fetch`,
  `AbortSignal`), so a consumer does not inherit a dependency tree. HTTP libraries
  (`axios`, `got`, `node-fetch`) were rejected.
- The CLI parses its flags with `node:util` `parseArgs` in strict mode; `commander` /
  `yargs` were rejected.
- **Tooling:** Yarn, Jasmine, nyc/c8 for coverage, ESLint 9 flat config: the same as
  `frontend/` ([frontend/stack.md](../../frontend/stack.md)).
- **Code style:** the repository's JavaScript rules apply: files declare one module, the
  entrypoint is the only script, public exports carry accurate JSDoc.
- **Rejected:** TypeScript source (types are delivered as generated `.d.ts`); a dual ESM +
  CommonJS build (needs a build step).

### Node.js version

The Node.js version is the one used by the frontend images under
`dockerfiles/vite_pidgey-base/`, declared once per package in `package.json` (`engines`) and
in the package README, so every JavaScript area runs one Node version. The specs do not pin
a number yet.

**Pending decision** (#226): which Node.js version and supported `engines` range, and whether every API the specs rely on (`fetch`, `AbortSignal` helpers, `parseArgs` strict mode, `require(esm)` for CommonJS consumers) exists on the lowest supported version.

## Ownership

| Path | Owner |
| --- | --- |
| `clients/nodejs/` (source, Jasmine specs, `package.json`, `yarn.lock`, `eslint.config.mjs`, the package README) | New agent `nodejs` |
| `clients/cli/` (`bin/`, source, Jasmine specs, `package.json`, `yarn.lock`, `eslint.config.mjs`) | New agent `cli` |
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
- Package versions are independent from each other (subject to #222) and from the backend
  version.

### What is breaking

| Package | Breaking |
| --- | --- |
| Library | Removing or renaming an export, an option or a `PidgeyError` field; changing a default (`maxRetries`, `retryAmbiguous`, `timeoutMs`, `retryDelayMs`); making the retry table retry **more** cases; changing a client-local code; dropping a Node.js major. |
| CLI | Removing or renaming a flag; changing an exit code or its meaning; changing what is printed on success; dropping a Node.js major. |

Not breaking: a new option, flag or `PidgeyError` field; a new client-local code (the docs
state that codes may be added); a case that becomes **not** retried; documentation.

## Compatibility

- The README states that the client works with **any Pidgey serving `POST /v1/messages`**.
  The client never calls `GET /v1/openapi.json` at runtime, so servers older than that route
  are supported.
- The client tolerates API growth
  ([errors-and-retry.md](errors-and-retry.md#forward-compatibility)); a new request field
  needs a client release, because the request payload is a whitelist
  ([library.md](library.md#message)).
- `/v1` itself is additive-only, enforced in backend CI
  ([openapi.md](openapi.md#v1-is-additive-only)).

## Publishing

- Both packages are published to the public npm registry within the epic: `pidgey-client`
  `0.1.0` first, then `pidgey-cli` `0.1.0` depending on it.
- **Already fixed:** whoever can publish is the same group that can deploy the backend. If
  the credential lives in CI, it sits in a CircleCI context restricted to the same security
  group as `pidgey-deploy`.
- npm credentials are never committed and never provided as repository secrets in a pull
  request; the publish step documents where the credential lives in
  `docs/agents/deployment/` (owned by `architect`).

**Pending decision** (#223): the publishing mechanism (CircleCI on a per-package tag, manual `npm publish`, or GitHub Actions with trusted publishing), tag naming, pre-publish gate, credential lifecycle and release/rollback procedure.

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
  allowlist glob that is too broad. The fuller smoke test (install the packed tarball,
  run `pidgey --help`, type-check the `.d.ts`) is not decided here: it stays with the extra
  checks pending decision ([testing.md](testing.md#extra-checks)).
- **No install scripts.** Neither package declares `preinstall`, `install` or `postinstall`
  scripts: installing them runs no code.
- **2FA and token.** 2FA is required on the maintainer's npm account and in each package's
  publishing settings. CI publishes with a **granular** npm token limited to `pidgey-client`
  and `pidgey-cli`, with an expiry date, never a classic long-lived automation token. Where
  the token lives, its rotation and the release procedure belong to the publishing pending
  decision above (#223).
- **Provenance.** The goal is provenance-attested releases: `npm publish --provenance`, or
  npm trusted publishing (OIDC), whenever the mechanism chosen in #223 supports it. If that
  mechanism cannot attest, the spec records the gap explicitly instead of dropping the goal
  silently; #223 confirms feasibility.
- **Frozen installs.** CI installs with the committed lockfile frozen
  (`yarn install --frozen-lockfile`, matching the Yarn tooling in [Stack](#stack)), so a
  release never resolves new dependency versions.
