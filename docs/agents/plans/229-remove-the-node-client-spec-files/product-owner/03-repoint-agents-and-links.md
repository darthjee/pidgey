# Repoint the agent files and fix remaining links

These edits are `architect`'s, since `.claude/agents/` is outside `product-owner`'s scope.

Rewrite the reading-order line in each agent file so it points only at the as-built doc. Drop "Read `docs/agents/specs/node-client.md` and `docs/agents/specs/node-client/*.md` while they exist, then" and the `(planned: #246)` or `(planned: #249)` note. Keep the rest unchanged:

- `product-owner` updates those docs in the same change set as the code;
- never implement a **Pending decision** by guessing.

Target wording:

- `.claude/agents/nodejs.md`: "Read the as-built `docs/agents/clients/nodejs.md`. `product-owner` updates it in the same change set as the code. Never implement a **Pending decision** by guessing."
- `.claude/agents/cli.md`: the same, with `docs/agents/clients/cli.md`.

Then run the CI-check `grep` from the index. Fix any other hit outside `docs/agents/issues/` and `docs/agents/plans/`, for example a link added by a sub-issue merged after this plan was written.

## Files to Change
- `.claude/agents/nodejs.md`: reading-order line (`architect`).
- `.claude/agents/cli.md`: reading-order line (`architect`).
- Any other file the `grep` reports outside `docs/agents/issues/` and `docs/agents/plans/`: fix the link.
