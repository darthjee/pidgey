# Credentials and URL

Add `cli/Credentials.js`, following `cli.md`, section Credentials and URL. It resolves the token and the base URL from the parsed flags and `env`.

**Token and URL sources:**
- the token comes from `--token-file`, otherwise from `PIDGEY_TOKEN`;
- the URL comes from `--url`, otherwise from `PIDGEY_URL`;
- a missing token or URL is a `UsageError` (no usage text).

**Reading the token file.** Use one descriptor: `fs.promises.open(path, 'r')`, then `handle.stat()` (an `fstat`), then a read of at most 4097 bytes, then `close` in a `finally`. The path is followed through symlinks by `open`.
- The file must be a regular file (`stat.isFile()`). Otherwise it is a `UsageError`: a FIFO, a device such as `/dev/stdin`, a socket or a directory.
- More than 4096 bytes read is a `UsageError` naming the 4 KiB limit.
- Trim exactly one trailing `\n` or `\r\n`, and nothing else.
- These are `UsageError`s, with a message that names the path and never the content:
  - an empty or whitespace-only token;
  - any control character (`/[\u0000-\u001f\u007f]/`) after the trim;
  - an `open` or `read` failure.
- **Mode warning:** when `platform !== 'win32'` and `stat.mode & 0o077`, write a warning to stderr naming the path, and continue.

Opening a FIFO with `'r'` blocks until a writer appears. Check whether `open` blocks in that case:
- if it does, open with `fs.constants.O_RDONLY | fs.constants.O_NONBLOCK`;
- if `O_NONBLOCK` is not available on the platform, `stat` the path first, but only to refuse non-regular files early. The `fstat` of the descriptor stays the check that counts.

Add the `Arguments_spec.js` cases from `testing.md` about token sources:
- precedence;
- the trim;
- a missing, unreadable or empty file;
- a file over 4 KiB;
- `/dev/stdin`, a FIFO made with `mkfifo` in a temp dir, a directory, and a symlink to a regular file (accepted);
- control characters (the message names the path only);
- the mode warning for a group- or other-readable file, and none for `win32`;
- a missing token or URL.

The specs can live in `Arguments_spec.js`, as `testing.md` places them, and call `Credentials.js` directly. Use temp files under `os.tmpdir()`, cleaned up after each spec.

## Files to Change
- `clients/cli/cli/Credentials.js`: new; token and URL resolution and the token-file rules.
- `clients/cli/specs/cli/Arguments_spec.js`: the credential cases.
- `clients/cli/types/node.d.ts`: `node:fs/promises` `open` (`FileHandle.stat`, `read`, `close`), `fs.constants`, and the `node:os`/`node:path` helpers the specs need, if typecheck covers them.
