# Command-line client (`pidgey-cli`)

The `pidgey` command, in `clients/cli/`. It is a thin layer over the library
([nodejs.md](nodejs.md)): the HTTP, the errors and the retries live in `pidgey-client`, not
here. Today it is a **scaffold**: it answers `--version` and `--help`, and everything else is a
usage error. The send flags come with #251. The rules are specified in
[specs/node-client/cli.md](../specs/node-client/cli.md) and
[specs/node-client/packaging.md](../specs/node-client/packaging.md); this page records what was
built and links the rules instead of copying them.

## Package

| Field | Value |
| --- | --- |
| Name | `pidgey-cli` |
| Version | `0.1.0`, the first release line; nothing is published before the epic's release. |
| Command | `pidgey`, mapped by `bin` to `bin/pidgey.js`. |
| Module system | ESM (`"type": "module"`). |
| Node.js | `engines.node` is `>=22.12.0`. |
| Published files | The `files` allowlist holds `bin/` and `cli/`; npm adds `package.json` and `README.md`. |
| Dependencies | `pidgey-client` only, see [The dependency on the library](#the-dependency-on-the-library). No other runtime dependency. |
| Install scripts | None (`preinstall`, `install`, `postinstall`, `prepare`). |
| License | MIT. |

`yarn.lock` is committed and the install runs with `--frozen-lockfile`, so a stale lockfile
fails the checks. Argument parsing uses `node:util` `parseArgs` in strict mode: there is no
argument-parsing dependency.

## Surface

| Command line | Result | Exit |
| --- | --- | --- |
| `pidgey --version` | `pidgey-cli <version> (pidgey-client <version>)` on stdout. The CLI version is its own `version` constant; the library version is the `VERSION` export of `pidgey-client`. | `0` |
| `pidgey --help` | The usage on stdout. | `0` |
| Anything else | `pidgey: <one-line reason>` and the usage on stderr, nothing on stdout. | `2` |

"Anything else" covers no argument, an unknown flag, a positional argument, a repeated flag,
and the send flags, which do not exist yet: the scaffold never answers "not implemented". When
`--help` and `--version` are both given, `--help` wins.

`run.js` treats **any** error thrown by the argument parser as a usage error, so a future
parser failure of another kind would also exit `2`; this is deliberate while the parser can
only fail on a usage mistake.

### Source layout

| File | Role |
| --- | --- |
| `bin/pidgey.js` | The entry point: calls `run` with the process streams and sets the exit code. No logic of its own. |
| `cli/run.js` | Runs the command: parses, prints, returns the exit code. It takes the streams as arguments, so the specs call it in-process. |
| `cli/Arguments.js` | The usage text, the `UsageError` class and the strict parse of the command line. |
| `cli/version.js` | The CLI `VERSION`, a string literal that a spec keeps equal to `package.json`. |
| `types/node.d.ts` | See [Typings](#typings). |

The CLI imports only the public exports of `pidgey-client`: never a relative `../nodejs/...`
import and never a deep import.

### Typings

`types/node.d.ts` is a minimal ambient typings shim for the few Node.js APIs the CLI uses
(`node:util` `parseArgs`, `process`) and for the `VERSION` export of `pidgey-client`, which
ships no typings until #250. It stands in for `@types/node` so that the scaffold adds no
dependency. The folder is excluded from ESLint.

**Pending decision:** whether to adopt `@types/node` as a devDependency, after the security
review of the new dependency, or to keep the shim until #250.

## Specs layout

Under `clients/cli/specs/`, mirroring the source:

- `specs/cli/Arguments_spec.js`, `run_spec.js` and `version_spec.js`: in-process specs of
  the parser, the command and the version constant;
- `specs/bin/Pidgey_spec.js`: spawns the real `bin/pidgey.js` and covers only what an
  in-process spec cannot prove: the exit status and the output streams for `--version` and
  for an unknown flag.

The coverage threshold is 100 % of lines, branches, functions and statements, over `bin/` and
`cli/`.

## Scripts

Run from `clients/cli/`. Each one exits non-zero on failure.

| Script | Does |
| --- | --- |
| `yarn lint` | ESLint over `bin`, `cli` and `specs`. |
| `yarn typecheck` | `tsc --allowJs --checkJs --noEmit` over `bin`, `cli` and `types`: the JSDoc types are checked, nothing is emitted. |
| `yarn test` | Jasmine over `specs/`, files named `*_spec.js`. |
| `yarn coverage` | `c8` running Jasmine. Writes `coverage/lcov.info` and **fails below 100 %**. |

## Pack check

As for the library ([nodejs.md](nodejs.md#pack-check)): `clients/cli/pack-files.txt` is the
committed list of expected files (`README.md`, `bin/pidgey.js`, `cli/Arguments.js`,
`cli/run.js`, `cli/version.js`, `package.json`), compared by `checks.sh` with
`npm pack --dry-run --json`. A file added or missing fails the checks.

The check packs the committed tree, so it sees the `file:../nodejs` dependency. The packed
dependency range is asserted separately, by the smoke test below.

## The dependency on the library

The CLI depends on `pidgey-client` in two forms, and only the second is ever published:

- **Committed:** `dependencies["pidgey-client"]` is exactly `file:../nodejs`. The CLI is always
  developed and tested against the current library source, never against a published copy.
- **Packed:** the tarball carries `^<version of clients/nodejs/package.json>`, so a published
  CLI requires the library version it was built with.

`clients/scripts/pack_cli.sh <out_dir>` makes the swap. It copies `clients/cli/` (without
`node_modules` and `coverage`) to a temporary directory, rewrites the dependency there, runs
`npm pack --ignore-scripts` there into `<out_dir>` and prints the tarball path. The committed
tree is never modified, so a failed pack cannot leave the file dependency swapped. The
publishing job (#253) reuses the script. See [clients.md](../clients.md#checks) for the checks
that guard both forms.

**Committed-file lint.** `checks.sh` fails when the committed dependency is anything other
than `file:../nodejs` (for example `^0.1.0`), before the CLI's own checks run.

**Fresh library copy.** Yarn copies a `file:` dependency into `node_modules` and does not
refresh it when `clients/nodejs/` changes. `checks.sh` therefore removes
`clients/cli/node_modules/pidgey-client` before the CLI's install and installs with
`--check-files`, so the CLI always runs against the current library source, also where the
`node_modules` volume persists between runs.

## Commands

| Command | Does |
| --- | --- |
| `make clients-checks` | Runs `checks.sh` in the compose `clients` service. `make tests` runs it too. |
| `docker compose run --rm clients clients/scripts/checks.sh` | The same, without `make`. |
| `docker compose run --rm clients sh -c "cd clients/cli && yarn <lint\|typecheck\|test\|coverage>"` | One script on its own. |

The one-script command needs the dependencies installed; `checks.sh` installs them. The
`clients` service and its `node_modules` volume per package are described in
[docker/compose.md](../docker/compose.md). Running `make clients-checks` removes the
`.gitkeep` files of `docker_volumes/clients_*_node_modules`; do not commit those deletions.

## CI

The CLI runs in the same job as the library: `clients_checks`, on both Node.js images
([nodejs.md](nodejs.md#ci)). Only the current-version instance uploads
`clients/cli/coverage/lcov.info` to Codacy, with the prefix `clients/cli`. After the checks,
the job asserts that the working tree is unchanged. See
[deployment/pipeline.md](../deployment/pipeline.md).
