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

Only `--version` and `--help` exist today; sending commands will follow.

```sh
pidgey --version   # pidgey-cli <version> (pidgey-client <version>)
pidgey --help      # prints the usage
```

Any other command line prints a one-line error and the usage on stderr, and exits with `2`.

## More

Source, issues and the design notes are in the
[repository](https://github.com/darthjee/pidgey/tree/main/clients/cli).
