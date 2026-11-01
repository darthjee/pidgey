# Move decision rationale into the deployment docs

Bring across the *why* behind each deployment decision. Most of it is in
`docs/agents/specs/deployment/security.md`, with some in `pipeline.md` (`Settled decisions`) and
`server.md`. Add it as short `Why:` notes to the matching sections of `docs/agents/deployment/*.md`.
Cover at least:

- **Host keys**: `SSH_KNOWN_HOSTS` is required because the deploy script would otherwise fall
  back to `StrictHostKeyChecking=no`; its value comes from `ssh-keyscan` with the fingerprint
  checked.
- **Deploy key**: dedicated ed25519, `restrict`, no `from=` because CircleCI IPs aren't stable.
- **CI context and its restriction**: attaching it in `config.yml` alone isn't enough; the
  expression restriction to semver tags, forked-PR secrets off.
- **Third-party code**: the build/context job split (check it is already complete in
  `pipeline.md#build-jobs-and-context-jobs`).
- **Shared `.env`**: modes `700` / `600`, linked outside `public/`, never through CI.
- **What ships**: the rule that any new top-level `source/` path goes in the exclude list or
  the shipped list in the same change.
- **Logs**: `bin/check-config` reports which check failed, never a value; the deploy never
  prints secrets.
- **New section `server.md#database-users`**: the two-user decision (#109), the grants table,
  why `REFERENCES`, never run `bin/drop-db` in production. Link to
  `operations.md#switch-to-separate-db-users` for the switch-over steps instead of repeating them.
- **New section `server.md#https`**: HSTS decision (#110/#115), HTTPS-only, no
  `includeSubDomains`/`preload` and why, the staged `max-age` rollout. Move the current HSTS
  bullet under `Web directory` here, or leave it there and link to the new section.
- **New section `pipeline.md#codacy`**: what the spec's `Codacy` section states about
  coverage upload and `.codacy.yml`, as implemented.
- `Database access in CI` (pipeline spec): if it's still accurate, add it to
  `pipeline.md#check-jobs`.
- `Never committed` / `To confirm` (server spec): make sure `server.md` states that no host
  facts are committed. To-confirm checks that are now done can be dropped.

## Files to Change
- `docs/agents/deployment/server.md` — new `Database users` and `HTTPS` sections; `Why:` notes for `.env`, host requirements, never-committed rule.
- `docs/agents/deployment/pipeline.md` — new `Codacy` section; `Why:` notes for context restriction, host keys, who can deploy; CI DB access.
- `docs/agents/deployment/variables.md` — `Why:` notes for `SSH_KNOWN_HOSTS` and the deploy key where they're described.
- `docs/agents/deployment/operations.md` — only if a recovery or verification fact from `edge-cases.md` / `release.md#verification` is missing.
