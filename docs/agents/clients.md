# Clients

Client libraries for Pidgey's `/v1` API, kept under `clients/`. They are Bearer-token clients
of `POST /v1/messages` only; the admin UI keeps its own cookie-authenticated client layer
([frontend/layout.md](frontend/layout.md)).

| File | Content |
| --- | --- |
| [nodejs.md](clients/nodejs.md) | The `pidgey-client` package: scaffold, current surface, scripts, coverage threshold, pack check, commands, CI job. |

The `clients/` folder:

| Path | Role | Status |
| --- | --- | --- |
| `clients/nodejs/` | The Node.js library `pidgey-client`. | Scaffolded: stub classes only. |
| `clients/cli/` | The command-line client `pidgey-cli`. | Coming in #249. |
| `clients/scripts/` | Shared scripts. `checks.sh` runs the checks of every package. | Built. |

The package folders are independent: there is no workspace tooling and no `package.json` at
the repository root, so `make tests` stays the single command that runs every check.

## Checks

`clients/scripts/checks.sh` is the single entry point of the checks. It takes no argument and
can be called from any working directory. It loops over a package list (today only `nodejs`)
and, for each package, runs `yarn install --frozen-lockfile`, `yarn lint`, `yarn typecheck`,
`yarn coverage` and the pack file-list check, stopping at the first failure.

Both `make clients-checks` (which `make tests` also runs) and the CircleCI job
`clients_checks` call this same script, so a green `make tests` means a green job. The one
difference is the Node.js version: `make clients-checks` runs on the development version
only, while CI also runs the floor version ([nodejs.md](clients/nodejs.md#ci)).

## Owners

| Owner | Owns |
| --- | --- |
| `nodejs` | The package folders under `clients/`. |
| `infra` | `clients/scripts/`, the `clients` compose service, the `make` targets and the CI job. |
| `product-owner` | These docs. |

## Not built yet

The behaviour of the clients is still in the spec
[specs/node-client.md](specs/node-client.md), which is removed once these docs cover it. Still
to come, per sub-issue of epic #219:

- `send`, options validation and error mapping (#247);
- retries and the retry table (#248);
- the CLI, the pack smoke test and the committed-file lint (#249);
- the generated types and the `.d.ts` check (#250);
- the integration job and publishing.
