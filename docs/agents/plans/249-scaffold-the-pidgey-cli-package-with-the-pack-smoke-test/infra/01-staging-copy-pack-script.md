# Staging-copy pack script

Add `clients/scripts/pack_cli.sh`, per `docs/agents/specs/node-client/packaging.md`
(section CLI to library dependency):

- `set -euo pipefail`; usage `pack_cli.sh <out_dir>`, exit non-zero without an argument;
  resolve `CLIENTS_DIR` from `$(dirname "$0")/..` like `checks.sh`, so it runs from any
  working directory.
- `STAGE="$(mktemp -d)"`, with `trap 'rm -rf "$STAGE"' EXIT`.
- Copy `clients/cli/` into `$STAGE` excluding `node_modules/` and `coverage/` (for example
  `tar -C clients/cli --exclude=./node_modules --exclude=./coverage -cf - . | tar -C "$STAGE" -xf -`;
  `rsync` is not in every image).
- Read the floor with `node -p 'require(process.argv[1]).version' clients/nodejs/package.json`
  (or `JSON.parse` of the file), and rewrite `dependencies["pidgey-client"]` in
  `$STAGE/package.json` with a short `node -e` script to `^<floor>`. Fail if the field is
  missing.
- In `$STAGE`, run `npm pack --ignore-scripts --pack-destination "<absolute out_dir>"`
  (`--json` to read the file name), and print the tarball's absolute path on stdout. All other
  output goes to stderr.
- The script never writes under `clients/`; the out dir is the caller's.

## Files to Change

- `clients/scripts/pack_cli.sh` — new, executable.
