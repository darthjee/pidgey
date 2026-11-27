# pidgey-cli

`pidgey` is the command-line client of the [Pidgey](https://github.com/darthjee/pidgey)
email-delivery service's `/v1` API. It is a thin layer over
[`pidgey-client`](https://github.com/darthjee/pidgey/tree/main/clients/nodejs): the HTTP
and the retries live in the library, not here.

Requires Node.js 22.12 or later.

## Install

```sh
npm install -g pidgey-cli
```

## Usage

```sh
pidgey --to user@example.com --subject Hello --text 'Hi!'

printf 'Hi!' | pidgey --to user@example.com --subject Hello

PIDGEY_URL=https://pidgey.example.com PIDGEY_TOKEN=pdg_example... \
  pidgey --to user@example.com --subject Hello --html '<p>Hi!</p>'

PIDGEY_URL=https://pidgey.example.com \
  pidgey --token-file /run/secrets/pidgey_token --to user@example.com --subject Hello --text 'Hi!'

pidgey --version   # pidgey-cli <version> (pidgey-client <version>)
pidgey --help      # prints the usage
```

### Flags

| Flag | Value |
| --- | --- |
| `--to` | Recipient address (required). |
| `--subject` | Subject (required). |
| `--text` | Plain-text body. |
| `--html` | HTML body. |
| `--token-file` | Path to a file holding the API token. |
| `--url` | Pidgey base URL. |
| `--idempotency-key` | Idempotency key, 1 to 255 printable ASCII characters. Without it, the library generates one per call. |
| `--retry-ambiguous` | Retry also when the server may have sent the message (risks a duplicate). |
| `--allow-insecure-http` | Allow an `http://` URL on a non-loopback host. |
| `--verbose` | Print the result on success. |
| `--help` | Print the usage. |
| `--version` | Print the CLI and library versions. |

An unknown or repeated flag, a positional argument or a missing `--to` or `--subject` prints the
reason and the usage on stderr and exits with `2`. Values are forwarded as given: `--text ''` is
sent, and the API decides whether an empty body is valid.

### Token and URL

- The token comes from `--token-file`, otherwise from the `PIDGEY_TOKEN` environment variable.
  **There is no `--token` flag**: command-line arguments are visible to every local user and land
  in shell history.
- The URL comes from `--url`, otherwise from `PIDGEY_URL`.
- A missing token or URL exits with `2`.
- The token file must be a regular file (a symlink to one is fine; a FIFO, a device such as
  `/dev/stdin` or a directory is refused) of at most 4096 bytes. One trailing newline (`\n` or
  `\r\n`) is trimmed. An empty token, or one that still holds a control character, is refused.
- On POSIX systems, a token file readable by group or others prints a warning on stderr and the
  send goes on.
- The token is never read from stdin, never prompted for and never printed.

### Body and stdin

- With `--text` and/or `--html`, those are the body and stdin is not read.
- With neither, stdin is the plain-text body, read as UTF-8.
- With neither and a terminal as stdin, the command exits with `2` instead of waiting for input.
- Stdin is read up to 1 MiB (1048576 bytes): one more byte exits with `2` and nothing is sent.
  `--text` and `--html` are not capped.
- A stdin that never closes (`tail -f | pidgey ...`) blocks forever: there is no stdin timeout.

### Output

- On success nothing is printed. With `--verbose`, one JSON line goes to stdout:
  `{"status":"sent","smtp":{"code":250,"message":"OK"},"replayed":false}`.
- On failure, stderr gets `pidgey: <code>: <message>`, then `pidgey: SMTP reply: <code> <message>`
  when the server gave one, then `pidgey: the message was possibly sent` when it may have been
  delivered. The message content, the token and the response body are never printed.

### Exit codes

| Code | Meaning |
| --- | --- |
| `0` | The message was sent. |
| `1` | The send failed or could not complete (network failures included). |
| `2` | Usage error: a bad command line, a missing token or URL, an unusable token file, a rejected `--url` or `--idempotency-key`, an unreadable or oversized stdin, or no body with a terminal stdin. |
| `130` | Interrupted by SIGINT (Ctrl-C). A request in flight is aborted. |

## More

Source, issues and the design notes are in the
[repository](https://github.com/darthjee/pidgey/tree/main/clients/cli).
