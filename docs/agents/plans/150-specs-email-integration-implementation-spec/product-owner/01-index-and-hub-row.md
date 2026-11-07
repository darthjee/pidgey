# Index and hub row

Create `docs/agents/specs/implement-email.md` and add its row to the specs hub.

The index contains, in order:

1. Title `# Implement Email`, purpose line, and the temporary marker.
2. **Scope** — SMTP connection endpoints (list, create, read, update, delete, activate),
   password encryption, SMTP transport and send flow, test email, frontend wiring, test
   infrastructure. Out: `POST /v1/messages`, password-recovery emails (later epics).
3. **Detail files** table: file, content, owner issue (`encryption.md` #151, `transport.md`
   #152, `endpoints.md` #153, `testing.md` #154, `frontend.md` #154).
4. **Building blocks** map: one row per block (password encryption, SMTP transport, send flow,
   connection endpoints, activate, test email, operator log, frontend wiring, test
   infrastructure) → governing permanent spec sections → detail file.
5. **Lifecycle rules** (rules 2–5 of the issue): spec issues put target-behavior decisions in
   the permanent specs and keep only implementation notes here; implementation issues tick off
   their items in the matching detail file in the same PR; the cleanup issue (last of #149)
   moves what is still useful into permanent docs, deletes `implement-email*`, removes the hub
   row, and proves with a grep that nothing under `docs/agents/` links to `implement-email`.
6. **Implementation sequence sketch** (non-binding): titles and dependencies only, e.g.
   password encryption service → connection endpoints (CRUD + activate) → SMTP transport → test
   email → frontend wiring; test infrastructure alongside transport; cleanup last. The real
   issues are created later by re-running `/arcanum-split-issue 149`.
7. **Unassigned** section (empty unless an open item fits no detail file).

Hub row in `docs/agents/specs.md` index table (appended last):
`| [implement-email](specs/implement-email.md) | Temporary: implementation spec of epic #149; removed when #149 closes. |`

## Files to Change
- `docs/agents/specs/implement-email.md` — new index.
- `docs/agents/specs.md` — new index row.
