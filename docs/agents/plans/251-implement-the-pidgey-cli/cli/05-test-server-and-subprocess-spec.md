# Test server and subprocess spec

Add `specs/support/TestServer.js`, a small in-process `node:http` server for the CLI specs. It does not import from `clients/nodejs/specs/`. It:
- listens on `127.0.0.1` with port `0`;
- records each request (method, path, headers, body);
- replies with a scripted status and JSON body;
- can delay a reply until told or until the connection closes, for SIGINT in flight;
- can answer `200` with keep-alive and leave the socket open, for the no-hang case;
- tracks open sockets, so `close()` destroys them.

Extend `specs/bin/Pidgey_spec.js` to the cases of its `testing.md` row. Spawn the real `bin/pidgey.js` with `PIDGEY_URL` pointing at the server and `PIDGEY_TOKEN` in the child's env.
- Exit `0` with empty stdout.
- Exit `1` with the error on stderr (for example `502 SMTP_PERMANENT_FAILURE`).
- Exit `2` on a usage error, and on `CLIENT_CONFIGURATION` (for example a malformed `--url`).
- A body piped through stdin reaching the server.
- A closed stdin and an empty stdin.
- More than 1 MiB written to a real pipe: exit `2` and no request recorded.
- SIGINT while the request is in flight: `child.kill('SIGINT')` once the server has seen the request; assert exit `130` and that the server saw the connection close.
- No-hang: the keep-alive `200`, with exit `0` within 5 s.

The spawned children inherit `NODE_V8_COVERAGE`, so c8 counts them. Keep the spawn helper free of a shell.

## Files to Change
- `clients/cli/specs/support/TestServer.js`: new.
- `clients/cli/specs/bin/Pidgey_spec.js`: the subprocess cases.
- `clients/cli/eslint.config.mjs`: only if `specs/support/` needs the same globals as the specs.
