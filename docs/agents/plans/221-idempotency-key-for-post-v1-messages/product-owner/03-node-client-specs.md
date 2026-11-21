# Node client specs

Update the epic #219 specs so the client uses the key; the client code itself is out of scope.

- `specs/node-client/library.md`: `send()` always sends `Idempotency-Key` (a `crypto.randomUUID()` generated once per call, reused across its retries); the `idempotencyKey` option (format check, `CLIENT_CONFIGURATION` on an invalid value); the `replayed` field of the success result.
- `specs/node-client/cli.md`: the `--idempotency-key <key>` flag.
- `specs/node-client/errors-and-retry.md`: the two codes in the never-retried list with their `possiblySent` values; in `retryAmbiguous`, replace the "tracked separately in #221" sentence with the actual behavior (default unchanged; duplicate-free ambiguous retries on a supporting server; a retry after `SMTP_TIMEOUT` replays the stored `504`).
- `specs/node-client/openapi.md`: the document describes the request header, the `Idempotent-Replayed` response header and the two codes.
- `specs/node-client/packaging.md`: compatibility note (an older server ignores the header).
- `specs/node-client/testing.md`: rows for one key reused across retries, the option and the flag passed through, an invalid caller key rejected locally, `replayed` mapped from the header, and the two codes never retried with their `possiblySent`.
- `specs/node-client.md`: drop any wording listing idempotency as future work.

## Files to Change

- `docs/agents/specs/node-client/library.md`
- `docs/agents/specs/node-client/cli.md`
- `docs/agents/specs/node-client/errors-and-retry.md`
- `docs/agents/specs/node-client/openapi.md`
- `docs/agents/specs/node-client/packaging.md`
- `docs/agents/specs/node-client/testing.md`
- `docs/agents/specs/node-client.md`
