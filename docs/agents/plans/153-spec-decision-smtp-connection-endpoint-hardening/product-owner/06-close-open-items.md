# Close implement-email/endpoints.md and pending summaries

Decision 7 and the issue's acceptance.

- `implement-email/endpoints.md`: tick or remove every open item, each pointing at the
  permanent spec now holding it; update the building blocks: add the deactivate handler
  (`Pidgey\Http\Handler\SmtpConnectionDeactivateHandler`, proposal), the host policy in the
  send flow (e.g. `Pidgey\Smtp\HostPolicy`, proposal), the test limit
  (`Pidgey\Repository\SmtpTestLimitRepository`, proposal), `Pidgey\Infra\LoggerFactory`,
  migrations `0010`/`0011`, the new configuration keys and the dev/test env change.
- `implement-email/transport.md`: add the resolve-once connect and host check to the send
  flow building block if it describes the connect step.
- `api/endpoints/pidgey-admin.md` Pending decisions summary: list only what remains (general
  rate limits and pagination, if referenced).
- Check `implement-email.md` index status lines for #153.

## Files to Change
- `docs/agents/specs/implement-email/endpoints.md`
- `docs/agents/specs/implement-email/transport.md`
- `docs/agents/specs/implement-email.md`
- `docs/agents/specs/api/endpoints/pidgey-admin.md`
