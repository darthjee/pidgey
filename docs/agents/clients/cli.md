# Command-line client (`pidgey-cli`)

The `pidgey` command, in `clients/cli/`. It is a thin layer over the library
([nodejs.md](nodejs.md)): the HTTP, the errors and the retries live in `pidgey-client`, not
here. It sends one message from the command line, with the token and the URL taken from a file
or the environment, the body from a flag or stdin, and an exit code per kind of outcome. The
rules are specified in [specs/node-client/cli.md](../specs/node-client/cli.md) and
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

```shell
pidgey --to user@example.com --subject Hello --text 'Hi!'
printf 'Hi!' | pidgey --to user@example.com --subject Hello
```

### Flags

| Flag | Value |
| --- | --- |
| `--to`, `--subject` | Recipient and subject. **Required**: a missing one is a parse error. |
| `--text`, `--html` | The body parts. |
| `--token-file` | Path to the file holding the token. |
| `--url` | The Pidgey base URL. |
| `--idempotency-key` | Forwarded as given to `send()`; the library validates it. |
| `--retry-ambiguous` | Passed as `retryAmbiguous: true`. |
| `--allow-insecure-http` | Passed as `allowInsecureHttp: true`. |
| `--verbose` | Prints the result on success. |
| `--help`, `--version` | The usage; `pidgey-cli <version> (pidgey-client <version>)`. Both exit `0`; `--help` wins when both are given. |

The flags are parsed with `parseArgs` in strict mode. An unknown flag (`--token` included), a
repeated flag, a positional argument, a flag without its value or a missing `--to` or
`--subject` is a **parse error**. Values are forwarded as given: `--text ''` is sent, and the
CLI validates nothing, the API does.

### Token and URL

- The token comes from `--token-file`, otherwise from `PIDGEY_TOKEN`; the URL from `--url`,
  otherwise from `PIDGEY_URL`. An empty `PIDGEY_TOKEN` counts as missing. There is no `--token`
  flag: the token is never taken from argv or stdin.
- The token file is opened with `O_NONBLOCK` and everything is checked on that one descriptor:
  it must be a regular file (a symlink to one is accepted; a FIFO, a device or a directory is
  refused before anything is read), and at most 4096 bytes are read (`4097` is refused). One
  trailing `\n` or `\r\n` is trimmed; an empty or whitespace-only token, or one still holding a
  control character, is refused. The messages name the path and the system error code, never
  the content.
- On POSIX, a file readable by group or others (`mode & 0o077`, from `fstat`) writes a warning to
  stderr (`pidgey: warning: token file '<path>' is readable by group or others`) and the send
  goes on. There is no check on `win32`.
- See [Credentials and URL](../specs/node-client/cli.md#credentials-and-url).

### Body and stdin

- With `--text` and/or `--html`, those parts are sent and stdin is not touched (`--html` alone
  sends no text part).
- With neither, stdin is the plain-text body, decoded as UTF-8 with replacement characters. A
  TTY stdin is a usage error instead of a blocking read.
- Stdin is capped at 1048576 bytes, counted before decoding; the read stops as soon as the
  input goes over, destroying stdin, and nothing is sent. Exactly 1 MiB is accepted. An
  unreadable stdin is a usage error. A SIGINT while stdin is being read stops the read and exits
  `130` without sending or printing.

### Output

- Success: stdout and stderr stay empty. With `--verbose`, one line goes to stdout: the
  `JSON.stringify` of what `send` resolves with (`status`, `smtp` and `replayed`; fields the API
  adds later are printed too).
- Failure, on stderr and in this order: `pidgey: <code>: <message>`; `pidgey: SMTP reply:
  <code> <message>` when the error has an SMTP reply; `pidgey: the message was possibly sent`
  when `possiblySent` is `true` (`ABORTED` included). Nothing else: never the message content,
  the token, the URL or the response body. A non-library error prints only
  `pidgey: unexpected error (<name>)`.
- A parse error prints `pidgey: <reason>` and then the usage text. Any other usage error prints
  only `pidgey: <reason>`: the usage text appears after a parse error only.

### Exit codes

| Code | Meaning |
| --- | --- |
| `0` | Sent (or `--help`, `--version`). |
| `1` | Any `PidgeyError` except `CLIENT_CONFIGURATION`: a rejected send, the network failures included. |
| `2` | A usage error: a parse error, a token, URL or stdin problem, or `CLIENT_CONFIGURATION` from the library. |
| `130` | SIGINT. |

On SIGINT, `bin/pidgey.js` aborts the controller whose signal is passed to `run`, which hands it
to `send()`; an `ABORTED` error raised while that signal is aborted exits `130`, with the
possibly-sent notice when the library says so. The handler is `once`: a second SIGINT gets
Node's default and ends the process at once. `bin/pidgey.js` never calls `process.exit()`: it
sets `process.exitCode` and the process ends by itself, without waiting on an idle keep-alive
socket.

### Source layout

| File | Role |
| --- | --- |
| `bin/pidgey.js` | The entry point: the SIGINT wiring, then `run` with the process streams, environment and signal; sets `process.exitCode`. No other logic. |
| `cli/run.js` | `run(argv, io)`: parses, resolves the credentials, reads the body, builds the client, sends, prints and returns the exit code. `io` (`env`, `stdin`, `stdout`, `stderr`, `signal`, and optional `createClient` and `platform`) is injected, so the specs call it in-process with a scripted client. |
| `cli/Arguments.js` | The usage text, the `UsageError` class (a parse error asks for the usage to be printed) and the strict parse of the command line. |
| `cli/Credentials.js` | The token and URL sources and the token file rules. |
| `cli/Body.js` | The body selection and the capped stdin read. |
| `cli/Output.js` | The `--verbose` line and the failure text. |
| `cli/ExitCode.js` | The exit codes and the mapping from what was thrown. |
| `cli/version.js` | The CLI `VERSION`, a string literal that a spec keeps equal to `package.json`. |
| `types/node.d.ts` | See [Typings](#typings). |

The CLI imports only the public exports of `pidgey-client`: never a relative `../nodejs/...`
import and never a deep import.

### Typings

`types/node.d.ts` is a minimal ambient typings shim for the few Node.js APIs the CLI uses
(`node:util` `parseArgs`, `node:fs` and `node:fs/promises` for the token file, and `process`).
It stands in for `@types/node` so that the CLI adds no dependency. The folder is excluded from
ESLint.

The shim declares Node.js only. `pidgey-client` is not declared there: it ships its own
declarations (`exports` `types` condition, see [nodejs.md](nodejs.md#types)), and the CLI's
typecheck resolves them from the `file:../nodejs` copy, which holds `dist/` because `checks.sh`
builds the library's declarations before the CLI's install
([clients.md](../clients.md#checks)). Outside `checks.sh`, run `yarn build:types` in
`clients/nodejs/` first.

**Decided in #251:** `@types/node` stays unadopted; the shim grew with the file-system APIs of
the token file instead of adding a dependency. Adopting `@types/node` remains possible later,
after a security review of the new dependency.

## Specs layout

Under `clients/cli/specs/`, mirroring the source:

- `specs/cli/Arguments_spec.js`, `Credentials_spec.js`, `Body_spec.js`, `ExitCode_spec.js`,
  `run_spec.js` and `version_spec.js`: in-process specs of the parser, the credentials, the
  body and stdin, the exit codes with the output, the command and the version constant.
  `Credentials_spec.js` is apart from `Arguments_spec.js` to keep each file under the lint's
  line limit. The unreadable token file is provoked with a symlink loop;
- `specs/bin/Pidgey_spec.js`: spawns the real `bin/pidgey.js` against the in-process server and
  covers only what an in-process spec cannot prove: the exit status and the streams, a body
  through a real pipe and its cap, SIGINT in flight (`130`, the connection aborted), and that
  the process exits promptly after a send;
- `specs/integration/Send_spec.js`: the end-to-end spec against a real Pidgey, run by
  `yarn test:integration` only ([clients.md](../clients.md#integration));
- `specs/support/TestServer.js`: the scripted `node:http` server the subprocess spec talks to
  (not a spec).

The coverage threshold is 100 % of lines, branches, functions and statements, over `bin/` and
`cli/`. One block of `Credentials.js` is excluded with `c8 ignore`: a failing `fstat` or read on
a descriptor that just opened cannot be provoked from a spec.

## Scripts

Run from `clients/cli/`. Each one exits non-zero on failure.

| Script | Does |
| --- | --- |
| `yarn lint` | ESLint over `bin`, `cli` and `specs`. |
| `yarn typecheck` | `tsc --allowJs --checkJs --noEmit` over `bin`, `cli` and `types`: the JSDoc types are checked, nothing is emitted. |
| `yarn test` | Jasmine over `specs/` (the directories of the unit and subprocess specs, not `specs/integration/`), files named `*_spec.js`. |
| `yarn coverage` | `c8` running Jasmine over the same specs. Writes `coverage/lcov.info` and **fails below 100 %**. |
| `yarn test:integration` | Jasmine over `specs/integration/` only: `Send_spec.js` runs the real `pidgey` as a subprocess against a real Pidgey and checks the delivery in Mailpit. Skipped without the `PIDGEY_INTEGRATION_*` environment; see [clients.md](../clients.md#integration). Never part of the coverage. |

## Pack check

As for the library ([nodejs.md](nodejs.md#pack-check)): `clients/cli/pack-files.txt` is the
committed list of expected files (`README.md`, `bin/pidgey.js`, `cli/Arguments.js`,
`cli/Body.js`, `cli/Credentials.js`, `cli/ExitCode.js`, `cli/Output.js`, `cli/run.js`,
`cli/version.js`, `package.json`), compared by `checks.sh` with
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
