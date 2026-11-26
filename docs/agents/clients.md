# Clients

Client libraries for Pidgey's `/v1` API, kept under `clients/`. They are Bearer-token clients
of `POST /v1/messages` only; the admin UI keeps its own cookie-authenticated client layer
([frontend/layout.md](frontend/layout.md)).

| File | Content |
| --- | --- |
| [nodejs.md](clients/nodejs.md) | The `pidgey-client` package: core (send, retries, errors, validation), current surface, the generated types and the contract spec, scripts, coverage threshold, pack check, the pack smoke test, commands, CI job. |
| [cli.md](clients/cli.md) | The `pidgey-cli` package and its `pidgey` command: surface (`--version`, `--help`, usage error), scripts, pack check, the dependency on the library, commands, CI. |

The `clients/` folder:

| Path | Role | Status |
| --- | --- | --- |
| `clients/nodejs/` | The Node.js library `pidgey-client`. | Built: send, retries, errors, validation, the types generated from the OpenAPI document and the contract spec. |
| `clients/cli/` | The command-line client `pidgey-cli`. | Scaffold built: `--version` and `--help`; the send flags come in #251. |
| `clients/scripts/` | Shared scripts. `checks.sh` runs the checks of every package; `pack_cli.sh` packs the CLI from a staging copy; `smoke_test.sh` is the pack smoke test. | Built. |

The package folders are independent: there is no workspace tooling and no `package.json` at
the repository root, so `make tests` stays the single command that runs every check.

## Checks

`clients/scripts/checks.sh` is the single entry point of the checks. It takes no argument and
can be called from any working directory. It loops over the package list `nodejs cli`, in this
order because the CLI consumes the library, and for each package runs, stopping at the first
failure:

1. for `cli` only, the **committed-file lint** (the `pidgey-client` dependency must be exactly
   `file:../nodejs`) and the removal of the library copy in `clients/cli/node_modules`, so the
   CLI installs the current library source ([cli.md](clients/cli.md#the-dependency-on-the-library));
2. `yarn install --frozen-lockfile --check-files`;
3. `yarn lint`, `yarn typecheck` and `yarn coverage`;
4. for `nodejs` only, `yarn build:types`, which emits the `.d.ts` declarations into `dist/`. It
   runs before the pack check, so the pack lists them, and before the CLI's install, so the
   CLI's `file:../nodejs` copy holds `dist/` and its typecheck resolves the library's
   declarations ([nodejs.md](clients/nodejs.md#types));
5. the pack file-list check against the package's `pack-files.txt`.

Once every package has passed, it runs the **pack smoke test** (`clients/scripts/smoke_test.sh`)
once. It packs both packages, with the CLI through `pack_cli.sh`, and installs the two tarballs
together in an empty project, offline and without install scripts, then fails on:

- a packed CLI dependency range other than `^<version of clients/nodejs/package.json>`;
- a broken `exports` map: `import` of `PidgeyClient` and `PidgeyError` from `pidgey-client`;
- a failing `require(esm)` of `pidgey-client` (`require('pidgey-client').PidgeyClient`);
- a `.d.ts` failure: `clients/nodejs/specs/types/consumer.ts`, copied into the project, must
  type-check (`strict`, `nodenext`, `skipLibCheck: false`, no `@types/node`) against the installed
  `pidgey-client`, so a wrong `types` path in `exports` or a broken declaration fails with
  `Smoke test failed: consumer.ts does not type-check against the installed pidgey-client`;
- `pidgey --version` printing anything but `pidgey-cli <version> (pidgey-client <version>)`,
  or `pidgey --help` not exiting `0`.

The smoke test needs neither the network nor `pidgey-client` on the registry, because the
library tarball satisfies the CLI's dependency.

Both `make clients-checks` (which `make tests` also runs) and the CircleCI job
`clients_checks` call this same script, so a green `make tests` means a green job. The one
difference is the Node.js version: `make clients-checks` runs on the development version
only, while CI also runs the floor version ([nodejs.md](clients/nodejs.md#ci)). CI also asserts
that the working tree is unchanged after the checks, which proves no pack touched it.

## Owners

| Owner | Owns |
| --- | --- |
| `nodejs` | `clients/nodejs/`. |
| `cli` | `clients/cli/`. |
| `infra` | `clients/scripts/` (including the pack script and the smoke test), the `clients` compose service (with its `source/openapi` mount), the `make` targets and the CI job. |
| `product-owner` | These docs. |

## Not built yet

The behaviour of the clients is still in the spec
[specs/node-client.md](specs/node-client.md), which is removed once these docs cover it. Still
to come, per sub-issue of epic #219:

- the CLI send flags, token handling and exit codes beyond `2` (#251);
- the integration job and publishing.
