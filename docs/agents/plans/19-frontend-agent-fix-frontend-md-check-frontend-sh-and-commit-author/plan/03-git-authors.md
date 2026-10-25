# Add per-agent commit authors
Add a top-level `git` block to `.claude/configuration/arcanum-repo-config.json`, in the same shape as citadel's (`../citadel/.claude/configuration/arcanum-repo-config.json`). Keep the existing keys (`auto-fix-all`, `next_step`, `version`) unchanged.

```json
"git": {
  "email": "darthjee+{agent}@gmail.com",
  "omit_model_coauthor": true,
  "merge_body_mode": "coauthors",
  "agents": {
    "architect": "darthjee+architect@gmail.com",
    "backend": "darthjee+backend@gmail.com",
    "frontend": "darthjee+frontend@gmail.com",
    "infra": "darthjee+infra@gmail.com",
    "product-owner": "darthjee+product-owner@gmail.com",
    "security": "darthjee+security@gmail.com",
    "email": "darthjee+email@gmail.com"
  }
}
```

The `agents` list matches `.claude/agents/*.md`. Check that the file is valid JSON (`jq .`). Optionally run `/arcanum-check-config git.agents.frontend` to confirm it resolves.

## Files to Change
- `.claude/configuration/arcanum-repo-config.json` — add the `git` block.
