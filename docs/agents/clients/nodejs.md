# Node.js client (`pidgey-client`)

The library for Pidgey's `/v1` API, in `clients/nodejs/`. Today it is a **scaffold**: the
package, its tooling and its checks exist, and the public surface is stub classes only. The
behaviour (`send`, retries, errors) is specified in
[specs/node-client/library.md](../specs/node-client/library.md) and
[specs/node-client/errors-and-retry.md](../specs/node-client/errors-and-retry.md), and is not
built yet.

## Package

| Field | Value |
| --- | --- |
| Name | `pidgey-client` |
| Version | `0.1.0`, the first release line; nothing is published before the epic's release. |
| Module system | ESM (`"type": "module"`). |
| Entry point | `exports` maps `.` to `lib/index.js`. |
| Node.js | `engines.node` is `>=22.12.0`. |
| Published files | The `files` allowlist holds `lib/` only; npm adds `package.json` and `README.md`. |
| Dependencies | No runtime dependency. Why: the library stays dependency-free to keep its supply chain empty. |
| Install scripts | None (`preinstall`, `install`, `postinstall`, `prepare`). Why: nothing runs at the consumer's install. |
| License | MIT. |

`yarn.lock` is committed and the install runs with `--frozen-lockfile`, so a stale lockfile
fails the checks.

## Current surface

`lib/index.js` re-exports two classes. Neither has a `send` method: the stubs never answer
"not implemented", and the behaviour arrives with #247.

- `PidgeyClient` stores a copy of its options. It validates nothing and does no network
  call.
- `PidgeyError` extends `Error` and sets `name` and `code`.

## Scripts

Run from `clients/nodejs/`. Each one exits non-zero on failure.

| Script | Does |
| --- | --- |
| `yarn lint` | ESLint over `lib` and `specs`. |
| `yarn typecheck` | `tsc --allowJs --checkJs --noEmit` over `lib`: the JSDoc types are checked, nothing is emitted. |
| `yarn test` | Jasmine over `specs/`, files named `*_spec.js`. |
| `yarn coverage` | `c8` running Jasmine. Writes `coverage/lcov.info` and **fails below 100 %** of lines, branches, functions and statements, over every file of `lib/` (also those no spec loads). |

The 100 % threshold applies from the first commit, so no later change retrofits it onto
merged code.

## Pack check

The package must publish exactly the files it declares. `clients/nodejs/pack-files.txt` is
the committed list of expected files: one path per line, sorted with `LC_ALL=C sort`, no blank
or comment lines. The check compares it with the `files[].path` values of
`npm pack --dry-run --json`; any file added or missing fails the checks and prints the diff.
Why: an allowlist alone does not stop a file from slipping into the tarball unnoticed, such as
a secret or a spec.

When the package legitimately gains or loses a published file, regenerate the list from
`clients/nodejs/` and review the diff by hand:

```sh
npm pack --dry-run --json \
  | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{for(const f of JSON.parse(s)[0].files)console.log(f.path)})' \
  | LC_ALL=C sort > pack-files.txt
```

If an unexpected file appears, narrow `files` instead of accepting it in the list.

## Commands

| Command | Does |
| --- | --- |
| `make clients-checks` | Runs `checks.sh` in the compose `clients` service. `make tests` runs it too. |
| `docker compose run --rm clients clients/scripts/checks.sh` | The same, without `make`. |
| `docker compose run --rm clients sh -c "cd clients/nodejs && yarn <lint\|typecheck\|test\|coverage>"` | One script on its own. |

The `clients` service is described in [docker/compose.md](../docker/compose.md).

## CI

The job `clients_checks` runs `clients/scripts/checks.sh` as a matrix over two Node.js
images:

- the floor, `cimg/node:22.12.0`, which proves the `engines` promise;
- the current one, `darthjee/circleci_node:0.2.1`, the image of the other check jobs.

It runs on every branch and tag, in parallel with the other check jobs, and `coverage_final`
requires it. Only the current-version instance uploads `clients/nodejs/coverage/lcov.info` to
Codacy, with the prefix `clients/nodejs`. The clients are not deployed, so no deploy job
requires it. See [deployment/pipeline.md](../deployment/pipeline.md).
