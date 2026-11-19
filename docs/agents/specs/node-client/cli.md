# CLI (`pidgey-cli`)

Spec of the command-line client in `clients/cli/`: the npm package `pidgey-cli`, exposing the
global command **`pidgey`** through its `bin` entry (`npm install -g pidgey-cli`). Packaging
is in [packaging.md](packaging.md).

```shell
pidgey --to user@example.com --subject Hello --text 'Hi!'

printf 'Hi!' | pidgey --to user@example.com --subject Hello

PIDGEY_URL=https://pidgey.example.com PIDGEY_TOKEN=pdg_example... \
  pidgey --to user@example.com --subject Hello --html '<p>Hi!</p>'
```

## Thin layer

The CLI parses flags, reads stdin, calls `client.send(...)` of
[`pidgey-client`](library.md) and maps the result to an exit code. It contains **no HTTP
code and no retry logic** of its own; both come from the library. If a CLI change needs to
know what a status or code means, the classification belongs in the library.

**Pending decision** (#222): which `pidgey-client` version range a published `pidgey-cli` depends on (see [packaging.md](packaging.md#cli-to-library-dependency)).

## Flags

Flags only, no subcommands: v1 has exactly one operation, and subcommands can be added later
without breaking the flags.

| Flag | Value | Maps to |
| --- | --- | --- |
| `--to` | Recipient address | `message.to` |
| `--subject` | Subject | `message.subject` |
| `--text` | Plain-text body | `message.text` |
| `--html` | HTML body | `message.html` |
| `--token` | API token | `token` option; falls back to `PIDGEY_TOKEN` |
| `--url` | Pidgey base URL | `baseUrl` option; falls back to `PIDGEY_URL` |
| `--retry-ambiguous` | Boolean | `retryAmbiguous: true` ([errors-and-retry.md](errors-and-retry.md#retryambiguous)) |
| `--verbose` | Boolean | Prints the result on success ([Output](#output)) |
| `--help` | Boolean | Prints the usage |
| `--version` | Boolean | Prints the CLI version |

- Parsed with `node:util` `parseArgs` in **strict** mode: an unknown flag is a usage error
  (exit `2`). No argument-parsing dependency.
- A **repeated** flag (`--to a --to b`) is a usage error (exit `2`), not "last wins": it must
  not suggest multiple recipients.
- Values are forwarded as given: `--text ''` is sent, and the API decides whether an empty
  body is valid. The CLI validates no value; the API is the validator.

## Credentials and URL

- `--token` falls back to `PIDGEY_TOKEN`; `--url` falls back to `PIDGEY_URL`. An explicit
  flag wins over the environment variable.
- A token on the command line is visible in process listings; the environment variable is
  the safer default, while the flag stays usable.
- A missing token (no `--token` and no `PIDGEY_TOKEN`) or URL (no `--url` and no
  `PIDGEY_URL`) is exit `2`.
- The CLI never reads a token from stdin, so piping a body never exposes the token in the
  pipeline.

**Pending decision** (#225): should the CLI warn when `--token` is used, or support reading the token from a file?

## Body and stdin

| Flags given | Body sent | Stdin |
| --- | --- | --- |
| `--text` and `--html` | Both parts | Not read |
| `--text` only | Plain-text part | Not read |
| `--html` only | HTML part only | Not read |
| Neither, stdin is not a TTY | Stdin as the plain-text body | Read |
| Neither, stdin is a TTY | — | Not read: exit `2` instead of blocking on input |

- Stdin is read as UTF-8; invalid byte sequences become replacement characters, and the API
  is the validator.
- An unreadable stdin or a closed pipe is exit `2`, not exit `1`.

**Pending decision** (#225): should the CLI cap the size of the body read from stdin (the API has no body-size limit yet, see [api/pending.md](../../api/pending.md))?

## Exit codes

| Code | Meaning |
| --- | --- |
| `0` | The message was sent. |
| `1` | The send failed or the client could not complete the call: any `PidgeyError` except `CLIENT_CONFIGURATION`, network failures included. |
| `2` | Usage error: unknown flag, repeated flag, missing required flag, missing token or URL in both flag and environment, a `CLIENT_CONFIGURATION` error from the library (for example a malformed `--url`), unreadable stdin, neither body flag with a TTY stdin. |
| `130` | Interrupted by SIGINT. |

- Separate codes let a shell script distinguish a rejected send from a mistyped command.
- SIGINT during a send aborts the request through the library's `signal`
  ([library.md](library.md#abort)) and exits `130`.

## Output

- **Success:** nothing is written, unless `--verbose` is passed.
- **Failure:** stderr gets the `code` and `message` of the `PidgeyError`, the SMTP reply when
  `error.smtp` is present, and a `possibly sent` notice when `possiblySent` is `true`.
- **Never printed:** message content, the token, or an SMTP password.
- Changing what is printed on success is a breaking change of the CLI
  ([packaging.md](packaging.md#what-is-breaking)).
