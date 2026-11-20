# Breaking list, hub and consistency pass

Finish the change set and check that the specs agree with each other.

- **`packaging.md`, "What is breaking":**
  - Library row: add "narrowing or removing the loopback exemption for `http://`".
  - CLI row: add "lowering the stdin cap".
  - Under "Not breaking", add: a new opt-in (such as an environment variable for
    `--allow-insecure-http`, or `--max-body-size`).
- **Hub, `docs/agents/specs/node-client.md`:** remove the #231 row from the
  pending-decisions table. Add a one-line mention of #233 wherever the hub lists related
  issues, if it has such a place; do not add a new section just for it.
- **Consistency pass:**
  - `grep -rn "#231" docs/agents/specs/` returns no `**Pending decision**` marker.
  - Every new option, flag, field and code-table change appears in each file that lists
    them: options table, flags table, exit codes, `PidgeyError` fields, client-local codes,
    Ambiguous failures.
  - Relative links resolve, including new anchors (for example `#response-body`,
    `#connections`).
  - Nothing reopens a #225 decision, and the #220, #222, #223 and #226 markers are
    unchanged.

## Files to Change
- `docs/agents/specs/node-client/packaging.md` — breaking / not-breaking lists.
- `docs/agents/specs/node-client.md` — pending table row removed.
- `docs/agents/specs/node-client/*.md` — consistency fixes only.
