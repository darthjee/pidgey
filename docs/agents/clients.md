# Clients

Client libraries for Pidgey's `/v1` API, kept under `clients/`. They are Bearer-token clients
of `POST /v1/messages` only; the admin UI keeps its own cookie-authenticated client layer
([frontend/layout.md](frontend/layout.md)).

| File | Content |
| --- | --- |
| [nodejs.md](clients/nodejs.md) | The `pidgey-client` package: core (send, retries, errors, validation), current surface, the generated types and the contract spec, scripts, coverage threshold, pack check, the pack smoke test, commands, CI job. |
| [cli.md](clients/cli.md) | The `pidgey-cli` package and its `pidgey` command: surface (flags, token and URL, body and stdin, output, exit codes), source and specs layout, scripts, pack check, the dependency on the library, commands, CI. |

The `clients/` folder:

| Path | Role | Status |
| --- | --- | --- |
| `clients/nodejs/` | The Node.js library `pidgey-client`. | Built: send, retries, errors, validation, the types generated from the OpenAPI document and the contract spec. |
| `clients/cli/` | The command-line client `pidgey-cli`. | Built: the send flags, the token file and environment, the body and capped stdin, the output, the exit codes and SIGINT. |
| `clients/scripts/` | Shared scripts. `checks.sh` runs the checks of every package; `pack_cli.sh` packs the CLI from a staging copy; `smoke_test.sh` is the pack smoke test; `integration.sh` runs the integration specs, with `integration_seed.mjs` and `integration_support.mjs`; `release_pack.sh`, `release_publish.sh` and `release_lib.sh` are the npm release scripts ([Releases](#releases)). | Built. |

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

## Integration

The unit specs run against an in-process server; the integration specs prove the clients
against a **real Pidgey**. Each package has one spec, `clients/nodejs/specs/integration/Send_spec.js`
(the library) and `clients/cli/specs/integration/Send_spec.js` (the `pidgey` CLI as a
subprocess), sharing `clients/scripts/integration_support.mjs`. Each sends a message through the
client and asserts, through Mailpit's HTTP API, that exactly one message arrived. The cases, in
both specs: a successful send; the same `Idempotency-Key` and body as a replay (`replayed: true`,
CLI exit `0`) with still one message; the same key with a different body as
`422 IDEMPOTENCY_KEY_REUSED`; `409 NO_SMTP_CONNECTION` for an organization without an SMTP
connection; `401 UNAUTHORIZED` for a revoked token. `409 IDEMPOTENCY_KEY_IN_USE` is not covered: it
needs a send still in flight. The rules are in
[specs/node-client/testing.md](specs/node-client/testing.md#integration).

**Stack.** A dedicated compose app service, `integration_app` (reached at `http://integration_app`,
no published host port), with its own database `pidgey_integration`, `USER_APPROVAL_REQUIRED=false`
and `SMTP_ALLOW_PRIVATE_HOSTS=true`, next to MySQL and the plain test Mailpit (`mailpit-plain`:
SMTP on `2025`, HTTP API on `9025`). `clients/scripts/integration_seed.mjs` creates the fixtures
through the admin JSON API, with fresh organizations per run, so reruns never collide.

**Run.** `make clients-integration` boots the stack and runs `clients/scripts/integration.sh`;
`make tests` runs it too, and so does the CircleCI job `clients_integration` (machine executor,
current Node.js only, after `clients_checks`): the `Makefile` target and the job call the same
script. The script runs `yarn test:integration` in each package.

**Environment handed to the specs** (by `integration.sh`):

| Variable | Value |
| --- | --- |
| `PIDGEY_INTEGRATION` | `required`, set only by `integration.sh`. |
| `PIDGEY_INTEGRATION_URL` | The Pidgey base URL, `http://integration_app`. |
| `PIDGEY_INTEGRATION_MAILPIT_URL` | The Mailpit HTTP API, `http://mailpit-plain:9025`. |
| `PIDGEY_INTEGRATION_TOKEN` | A `send` token of organization A, which has an active Mailpit connection. |
| `PIDGEY_INTEGRATION_REVOKED_TOKEN` | A revoked `send` token of organization A. |
| `PIDGEY_INTEGRATION_NO_CONNECTION_TOKEN` | A `send` token of organization B, which has no SMTP connection. |

**Skip or required.** With `PIDGEY_INTEGRATION_URL` unset and `PIDGEY_INTEGRATION` not `required`,
every case is `pending`, so a local `yarn test:integration` without a stack passes by skipping.
With `PIDGEY_INTEGRATION=required` (CI and `make`), a missing variable fails the run, naming
the variable and never printing a token.

**One package against a running stack.** With the stack up and the six variables exported (the
seed's output), run `yarn test:integration` from `clients/nodejs/` or `clients/cli/`. The
integration specs are excluded from `yarn test` and `yarn coverage`, so they never count toward
the 100 % threshold or the Codacy upload.

## Releases

The publish pipeline is built, but **nothing is published yet**: on 2026-11-27 neither package
name existed on npm.

**First release (#254).** `0.1.0` of both packages is a one-off manual publish from the
maintainer's machine, after the merge of its release change. The maintainer publishes, library
first and with 2FA, the tarballs that `release_pack.sh` produces and the dry run of
`release_publish.sh` checks
([runbook](deployment/operations.md#publish-the-first-client-release-manually)). There are no
`0.0.0` placeholders and no npm token setup, and no release tag is pushed yet. CI publishing
starts with the next release, after npm trusted publishing (#266)
([why](specs/node-client/packaging.md#first-release-010)).

In CI, each package is released on its own, by its own tag (`pidgey-client-vX.Y.Z` or
`pidgey-cli-vX.Y.Z`): a CircleCI pack job, which holds no secret, then a publish job, which as
built holds an npm token
([deployment/pipeline.md](deployment/pipeline.md#npm-publish-chain)). Their logic is in
`clients/scripts/`, so it also runs locally:

| Script | Role |
| --- | --- |
| `release_pack.sh` | The pack job: the tag, version and changelog gates, an install without lifecycle scripts, the pack and the file-list check. |
| `release_publish.sh` | The publish job: packs a reference from the checkout and publishes only if the pack job's tarball is byte-identical to it. It has a [dry-run mode](deployment/operations.md#dry-run-a-client-release-locally). |
| `release_lib.sh` | Shared by the two, so both read the tag, pack and check the file list in the same way. Sourced, never run. |

Both pack the CLI through `pack_cli.sh`
([clients/cli.md](clients/cli.md#the-dependency-on-the-library)).

**Changelog.** Each package has a `CHANGELOG.md`: an empty `## Unreleased` heading and, below
it, the `## 0.1.0` entry of the first release. The release gate reads the `## X.Y.Z` heading of
the version being released (optionally `## X.Y.Z - YYYY-MM-DD`, without brackets around the
version). The file does not ship in the tarball.

**Procedure and limits.** The library is released first; in CI releases, the CLI tag is pushed
only after the library is published
([operations.md](deployment/operations.md#release-a-client-package)). The packages carry
[no provenance](deployment/known-gaps.md#npm-packages-published-without-provenance), and
[publishing with the npm token ends in January 2027](deployment/known-gaps.md#npm-token-publishing-ends-in-january-2027),
so CI publishing waits for #266. The rules are in
[specs/node-client/packaging.md](specs/node-client/packaging.md#publishing).

## Owners

| Owner | Owns |
| --- | --- |
| `nodejs` | `clients/nodejs/`. |
| `cli` | `clients/cli/`. |
| `infra` | `clients/scripts/` (including the pack script, the smoke test, the integration scripts and the release scripts), the `clients` compose service (with its `source/openapi` mount), the `integration_app` compose service, the `make` targets and the CI jobs. |
| `product-owner` | These docs. |

## Still to come

The behaviour of the clients is still in the spec
[specs/node-client.md](specs/node-client.md), which is removed once these docs cover it (#229).
Not done yet:

- the manual publish of the first release, `0.1.0` of both packages (#254), see
  [Releases](#releases);
- the switch to npm trusted publishing (#266), which CI publishing waits for.
