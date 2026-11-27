# Deployment docs and spec update

`docs/agents/deployment/` is owned by `architect`, who reviews this step. Follow the existing style of each file.

- `pipeline.md`:
  - a new section on the npm publish chain: the four jobs, what each runs, `requires`, the tag filters and why they never overlap `deploy_tags`, the scripts and their dry-run mode;
  - extend "Build jobs and context jobs" (pack jobs have no context; publish jobs install nothing);
  - extend "Context restriction" with `pidgey-npm` and its expression `pipeline.git.tag =~ "^pidgey-(client|cli)-v[0-9]+\.[0-9]+\.[0-9]+$"`;
  - extend "Serial group" with `<< pipeline.project.slug >>/pidgey-npm`;
  - extend "Who can deploy" (or add a sibling) for "who can publish".
- `variables.md`: the `pidgey-npm` context and `NPM_TOKEN` (granular token, packages `pidgey-client` and `pidgey-cli`, read and write, expiry, never a project variable, forked-PR secrets off).
- `operations.md`:
  - "Release a client package": release PR (bump `version` plus the `CHANGELOG.md` entry), tag the merge commit on `main`, push the tag(s), check the workflow and `npm view`;
  - "Rerun a failed publish" (idempotent no-op);
  - "Rotate the npm token" (four steps from the spec, plus the 401/403 symptom);
  - "Deprecate a client release" (`npm deprecate`, from the maintainer's machine with 2FA, then a fix-forward patch);
  - "Unpublish a client release" (leaked secret only, 72-hour window, rotate the secret);
  - "Dry-run a client release locally" (`release_pack.sh` + `release_publish.sh --dry-run`).
- `docs/agents/deployment.md` (the index): link the new sections if it lists them.
- `docs/agents/specs/node-client/packaging.md`: point the "Docs for the implementation sub-issue" section at the written docs, and add the dry-run mode to "CI jobs, per package".

## Files to Change
- `docs/agents/deployment/pipeline.md`
- `docs/agents/deployment/variables.md`
- `docs/agents/deployment/operations.md`
- `docs/agents/deployment.md` — only if it indexes sections
- `docs/agents/specs/node-client/packaging.md`
