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
- The client tolerates API growth: the success body is passed through unchanged; unknown
  `SMTP_*` codes follow the status rows of the retry table; any other unknown code is
  surfaced as received and never retried
  ([errors-and-retry.md](errors-and-retry.md#forward-compatibility)).
- A new request field needs a client release: the request payload is a whitelist.
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

**Pending decision** (#225): supply-chain settings (npm 2FA on the packages, provenance) and confirming that devDependencies (`openapi-typescript`, `typescript`, the contract-test schema validator) never ship.
