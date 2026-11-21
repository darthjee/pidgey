# CLI (`pidgey-cli`)

Spec of the command-line client in `clients/cli/`: the npm package `pidgey-cli`, exposing the
global command **`pidgey`** through its `bin` entry (`npm install -g pidgey-cli`). Packaging
is in [packaging.md](packaging.md).

```shell
pidgey --to user@example.com --subject Hello --text 'Hi!'

printf 'Hi!' | pidgey --to user@example.com --subject Hello

PIDGEY_URL=https://pidgey.example.com PIDGEY_TOKEN=pdg_example... \
  pidgey --to user@example.com --subject Hello --html '<p>Hi!</p>'

PIDGEY_URL=https://pidgey.example.com \
  pidgey --token-file /run/secrets/pidgey_token --to user@example.com --subject Hello --text 'Hi!'
```

## Thin layer

The CLI parses flags, reads stdin, calls `client.send(...)` of
[`pidgey-client`](library.md) and maps the result to an exit code. It contains **no HTTP
code and no retry logic** of its own; both come from the library. If a CLI change needs to
know what a status or code means, the classification belongs in the library.

A published `pidgey-cli` depends on `pidgey-client` through a caret range with a derived
floor, swapped in at pack time; the rules are in
[packaging.md](packaging.md#cli-to-library-dependency).

## Flags

Flags only, no subcommands: v1 has exactly one operation, and subcommands can be added later
without breaking the flags.

| Flag | Value | Maps to |
| --- | --- | --- |
| `--to` | Recipient address | `message.to` |
| `--subject` | Subject | `message.subject` |
| `--text` | Plain-text body | `message.text` |
| `--html` | HTML body | `message.html` |
| `--token-file` | Path to a file holding the API token | `token` option; `PIDGEY_TOKEN` when absent ([Credentials and URL](#credentials-and-url)) |
| `--url` | Pidgey base URL | `baseUrl` option; falls back to `PIDGEY_URL` |
| `--idempotency-key` | Key (1 to 255 printable ASCII characters) | `idempotencyKey` option of `send()`; without it the library generates one per call ([library.md](library.md#idempotency-key)) |
| `--retry-ambiguous` | Boolean | `retryAmbiguous: true` ([errors-and-retry.md](errors-and-retry.md#retryambiguous)) |
| `--allow-insecure-http` | Boolean | `allowInsecureHttp: true`: allows an `http://` `--url` on a non-loopback host ([library.md](library.md#cleartext-transport)) |
| `--verbose` | Boolean | Prints the result on success ([Output](#output)) |
| `--help` | Boolean | Prints the usage |
| `--version` | Boolean | Prints `pidgey-cli <version> (pidgey-client <version>)`, for example `pidgey-cli 0.1.0 (pidgey-client 0.1.3)`; the library version is its `VERSION` export ([library.md](library.md#exports)) |

- Parsed with `node:util` `parseArgs` in **strict** mode: an unknown flag is a usage error
  (exit `2`). No argument-parsing dependency.
- `--version` prints the **resolved** library version, not the declared range: under a caret
  range, that is the version a bug report needs.
- A **repeated** flag (`--to a --to b`) is a usage error (exit `2`), not "last wins": it must
  not suggest multiple recipients.
- `--idempotency-key` is forwarded as given; an invalid value is the library's
  `CLIENT_CONFIGURATION` (exit `2`). The key is not printed.
- Values are forwarded as given: `--text ''` is sent, and the API decides whether an empty
  body is valid. The CLI validates no value; the API is the validator.
- `--allow-insecure-http` has no environment variable; one can be added later without a
  breaking change. Without the flag, an `http://` `--url` on a non-loopback host exits `2`
  (the library's `CLIENT_CONFIGURATION`), and the message names the cleartext reason, never
  the token. It does not include the URL (or strips its userinfo), so credentials in
  `http://user:pass@host` are never echoed. With an `https://` URL the flag is accepted and has no effect.

## Credentials and URL

- **Token sources**, in precedence order:
  1. `--token-file <path>`: the content of the file.
  2. `PIDGEY_TOKEN`.

  An explicit `--token-file` wins over `PIDGEY_TOKEN`, as `--url` wins over `PIDGEY_URL`.
- **The token is never taken from argv.** Command-line arguments are readable by every local
  user (`ps`, `/proc/<pid>/cmdline`) and land in shell history; the environment is readable
  only by the same user and root. There is no `--token` flag: it was removed before the first
  release, while removing it was free; removing it after `0.1.0` would have been breaking.
  `--token` is an unknown flag (exit `2`).
- **Reading the token file.** The path must resolve, after following symlinks, to a
  **regular file**: a device, FIFO, socket or directory (for example `/dev/stdin`,
  `/dev/zero` or a named pipe) is a usage error (exit `2`), which keeps the token off stdin.
  At most **4 KiB** is read; a larger file is a usage error (exit `2`). The token is the
  file's content with **one** trailing newline (`\n` or `\r\n`) trimmed; no other whitespace
  is trimmed. A missing or unreadable file, a token that is empty or whitespace-only after
  trimming, or a token that still contains any control character (CR, LF, NUL, tab or any
  other) after trimming, is a usage error (exit `2`). The message names the path, never the
  content.
- **Permissions.** On POSIX systems, the mode is checked with `fstat` on the descriptor the
  token is actually read from (not a separate `stat` of the path, which a swap between check
  and read could defeat). A token file readable by group or others
  (`mode & 0o077`) prints a warning to stderr and the send continues. There is no check on
  Windows. Refusing such a file (as `ssh` does for keys) was rejected because
  orchestrator-mounted secrets often carry broad modes.
- A missing token (no `--token-file` and no `PIDGEY_TOKEN`) or URL (no `--url` and no
  `PIDGEY_URL`) is exit `2`.
- The token is never read from stdin (so piping a body never exposes it in the pipeline),
  never prompted for, and never printed.
- **Rejected:** keeping `--token` with a stderr warning (noisy, cannot be silenced, ignored in
  practice); keeping `--token` documented only; an interactive `/dev/tty` prompt (unfit for
  scripting, and it conflicts with "missing token → exit `2`"); a config file or keychain
  with `pidgey login` (a feature of its own, out of scope for `0.1.0`); a `PIDGEY_TOKEN_FILE`
  variable (not needed now; it can be added later without a breaking change).

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

### Stdin size cap

- The CLI stops reading stdin after **1 MiB** (1048576 bytes), counted in bytes before UTF-8
  decoding.
  - Exactly 1 MiB is accepted; one byte more exits `2` with a message naming the limit, and
    nothing is sent.
  - Reading stops as soon as the input goes over the cap; the rest is not drained.
- `--text`/`--html` are not capped: the shell already limits argument size.
- A stdin that never closes (`tail -f | pidgey`) blocks forever. There is no stdin timeout,
  and the README says so.
- The library does **not** cap `text`/`html` ([library.md](library.md#message)).
- The cap is not configurable; a `--max-body-size` flag can be added later without a breaking
  change.
- The cap equals the API's request body limit of 1 MiB
  ([api/endpoints/sending.md](../../api/endpoints/sending.md#size-limits)). The body the CLI
  sends is the JSON encoding of the message, which can be larger than the stdin text (escaping,
  the other fields): a stdin under the cap whose JSON body goes over 1 MiB is not caught by
  the CLI and gets the API's `413 HTTP_413` (exit `1`).
- **Rejected:** a library cap (it duplicates a rule the API owns); a configurable cap (nobody
  needs it yet); no cap (no protection against an endless pipe such as `pidgey < /dev/zero`).

### Process exit

The CLI process exits right after a send, without waiting on an idle keep-alive socket
([library.md](library.md#connections)).

## Exit codes

| Code | Meaning |
| --- | --- |
| `0` | The message was sent. |
| `1` | The send failed or the client could not complete the call: any `PidgeyError` except `CLIENT_CONFIGURATION`, network failures included. |
| `2` | Usage error: unknown flag, repeated flag, missing required flag, missing token (no `--token-file` and no `PIDGEY_TOKEN`) or URL (no `--url` and no `PIDGEY_URL`), an unreadable, empty, oversized (over 4 KiB) or non-regular token file, a token file holding a control character, a `CLIENT_CONFIGURATION` error from the library (for example a malformed `--url`, or a non-loopback `http://` `--url` without `--allow-insecure-http`), unreadable stdin, stdin over 1 MiB, neither body flag with a TTY stdin. |
| `130` | Interrupted by SIGINT. |

- Separate codes let a shell script distinguish a rejected send from a mistyped command.
- SIGINT during a send aborts the request through the library's `signal`
  ([library.md](library.md#abort)) and exits `130`.

## Output

- **Success:** nothing is written, unless `--verbose` is passed.
- **Failure:** stderr gets the `code` and `message` of the `PidgeyError`, the SMTP reply when
  `error.smtp` is present, and a `possibly sent` notice when `possiblySent` is `true`. The
  notice also applies to an `ABORTED` (SIGINT, exit `130`) that reports `possiblySent: true`.
- **Never printed:** message content, the token, an SMTP password, or the response `body` of
  the error ([errors-and-retry.md](errors-and-retry.md#response-body)).
- Changing what is printed on success is a breaking change of the CLI
  ([packaging.md](packaging.md#what-is-breaking)).
