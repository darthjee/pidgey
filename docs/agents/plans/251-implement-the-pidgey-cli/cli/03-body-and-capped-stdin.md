# Body and capped stdin

Add `cli/Body.js`, following `cli.md`, section Body and stdin.

**Body selection:**
- with `--text` and/or `--html`, use those parts and never touch stdin;
- with neither and `stdin.isTTY`, it is a `UsageError` (no blocking);
- otherwise read stdin as the plain-text body.

**Reading stdin:**
- iterate the readable's chunks as `Buffer`s and count bytes before decoding;
- as soon as the total goes over 1048576, call `stdin.destroy()`, stop and raise a `UsageError` naming the 1 MiB limit (nothing is sent). Exactly 1048576 bytes is accepted;
- decode the joined buffer with `TextDecoder('utf-8')` (non-fatal), so invalid sequences become U+FFFD;
- a read error (an unreadable stdin or a closed pipe) is a `UsageError`.

If the caller's `signal` aborts while stdin is being read, stop reading and let `run` exit `130` without sending.

Add `specs/cli/Body_spec.js` with the cases of its `testing.md` row. Use `Readable.from([...])` (and an erroring readable) with `isTTY` set as needed. For "reading stopped at the cap", feed more than 1 MiB and assert the extra chunks were never pulled.

## Files to Change
- `clients/cli/cli/Body.js`: new; body selection and the capped stdin read.
- `clients/cli/specs/cli/Body_spec.js`: new.
- `clients/cli/types/node.d.ts`: `Buffer`, a readable with `isTTY` and `destroy`, and `node:stream` `Readable.from` for the specs.
